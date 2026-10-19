<?php

namespace App\Console\Commands;

use App\Models\CrmToken;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

/**
 * Excel'dagi har bir mashina raqami bo'yicha Zanjeer CRM'dagi zakazlarni
 * (/orders/orders?filter[search]=...) qidirib, ularda eng ko'p qatnashgan
 * OPERATION menejerini oxirgi "Manager" ustuniga yozadi, so'ng qatorlarni
 * kompaniya nomi bo'yicha alifbo tartibida saralab, faylni joyida (yoki
 * --output berilsa o'sha yerga) saqlaydi.
 *
 * Har xil scrape fayllarda ustun nomlari turlicha (Mashina raqami / Plate /
 * Avtomobil raqami / Рег.номер, Company / Company Name), shuning uchun ular
 * avtomatik aniqlanadi. Kompaniya ustuni bo'lmasa (masalan kazakhstan-china),
 * faqat Manager qo'shiladi, saralanmaydi.
 *
 * CRM'da zakazlar uchun JSON API yo'q — sahifa HTML bo'lib keladi va har
 * bir zakaz alohida karta. Bitta asosiy zakaz va uning sub-zakazlarida
 * (#KGS..., #TLS...) operation bir xil, shuning uchun har bir asosiy zakaz
 * (root) bir marta sanaladi.
 *
 * Sessiya `crm_tokens` jadvalidan olinadi (`crm:login`). Eskirgan bo'lsa,
 * bir marta `crm:login` qilib qayta uriniladi; bu ham o'xshamasa, qolgan
 * raqamlar so'ralmaydi (Manager bo'sh qoladi), fayl baribir saralanadi.
 */
class FillPlateOperatorsCommand extends Command
{
    protected $signature = 'crm:plate-operators
                            {file : To\'liq yo\'l, masalan storage/app/import_qozoq/qozoq_..._tekshirilgan.xlsx}
                            {--output= : Natijani shu faylga yozish (bo\'sh bo\'lsa asl fayl ustidan yoziladi)}
                            {--plate-column= : Mashina raqami ustuni sarlavhasi (bo\'sh bo\'lsa avtomatik)}
                            {--sort-column= : Saralash ustuni sarlavhasi (bo\'sh bo\'lsa avtomatik)}
                            {--delay=500 : So\'rovlar orasidagi pauza (ms)}';

    protected $description = 'Mashina raqami bo\'yicha Zanjeer CRM zakazlaridan eng ko\'p OPERATION menejerni topib, "Manager" ustuniga yozadi va Company bo\'yicha saralaydi';

    private const ORDERS_URL = 'https://crm.zanjeer.uz/orders/orders';

    private const PER_PAGE = 20;

    private const MANAGER_HEADER = 'Manager';

    private const NOT_FOUND = 'topilmadi';

    private const PLATE_HEADERS = ['Mashina raqami', 'Plate', 'Avtomobil raqami', 'Рег.номер'];

    private const COMPANY_HEADERS = ['Company', 'Company Name'];

    private array $cookies = [];

    private bool $reloggedIn = false;

    /** CRM'ga kirib bo'lmasa true — qolgan raqamlar uchun so'rov yuborilmaydi */
    private bool $sessionBroken = false;

    public function handle(): int
    {
        $file = $this->argument('file');
        $output = $this->option('output') ?: $file;

        if (! file_exists($file)) {
            $this->error("Fayl topilmadi: {$file}");

            return self::FAILURE;
        }

        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, false, false, false);

        if (count($rows) < 2) {
            $this->warn("Faylda ma'lumot yo'q: {$file}");

            if ($output !== $file) {
                copy($file, $output);
            }

            return self::SUCCESS;
        }

        $headers = array_map(fn ($h) => trim((string) $h), $rows[0]);
        $plateCol = self::findColumn($headers, $this->option('plate-column') ? [$this->option('plate-column')] : self::PLATE_HEADERS);
        $sortCol = self::findColumn($headers, $this->option('sort-column') ? [$this->option('sort-column')] : self::COMPANY_HEADERS);

        if ($plateCol === null) {
            $this->error('Mashina raqami ustuni topilmadi: ' . json_encode($headers, JSON_UNESCAPED_UNICODE));

            return self::FAILURE;
        }

        // Fayl qayta ishlansa, "Manager" ustuni ikkinchi marta qo'shilmasin
        $managerCol = array_search(self::MANAGER_HEADER, $headers, true);

        if ($managerCol === false) {
            $managerCol = count($headers);
            $headers[] = self::MANAGER_HEADER;
        }

        $data = array_slice($rows, 1);
        $cache = [];

        try {
            $this->loadCookies();
        } catch (Throwable $e) {
            $this->error($e->getMessage());
        }

        foreach ($data as $i => $row) {
            if ($this->sessionBroken) {
                $data[$i][$managerCol] = '';

                continue;
            }

            $plate = $this->normalizePlate((string) ($row[$plateCol] ?? ''));

            // juda qisqa qiymat (masalan "01") CRM'da minglab natija qaytaradi
            if (mb_strlen($plate) < 5) {
                $data[$i][$managerCol] = self::NOT_FOUND;

                continue;
            }

            if (! array_key_exists($plate, $cache)) {
                try {
                    $cache[$plate] = $this->topOperation($plate) ?? self::NOT_FOUND;
                } catch (Throwable $e) {
                    $this->error("{$plate}: " . $e->getMessage());

                    if ($this->sessionBroken) {
                        $data[$i][$managerCol] = '';

                        continue;
                    }

                    $cache[$plate] = 'ERROR';
                }

                $this->line("{$plate} => {$cache[$plate]}");
            }

            $data[$i][$managerCol] = $cache[$plate];
        }

        if ($sortCol !== null) {
            usort($data, function (array $a, array $b) use ($sortCol) {
                $x = self::companySortKey((string) ($a[$sortCol] ?? ''));
                $y = self::companySortKey((string) ($b[$sortCol] ?? ''));

                // kompaniya nomi bo'sh qatorlar oxiriga tushadi
                if ($x === '' || $y === '') {
                    return ($x === '') <=> ($y === '');
                }

                return strnatcmp($x, $y);
            });
        }

        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($data, null, 'A2', true);

        (new Xlsx($spreadsheet))->save($output);

        $this->info("Tayyor: {$output} (" . count($data) . ' qator, ' . count($cache) . ' ta noyob raqam)');

        if ($this->sessionBroken) {
            $this->error('CRM\'ga kirib bo\'lmadi — Manager ustuni to\'liq to\'ldirilmadi.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Raqam bo'yicha barcha sahifalardagi zakazlarni yig'ib, eng ko'p
     * qatnashgan OPERATION ismini qaytaradi (natija bo'lmasa null).
     */
    private function topOperation(string $plate): ?string
    {
        $html = $this->fetchPage($plate, 1);

        if (! preg_match('/Showing\s+\d+\s+to\s+\d+\s+of\s+([\d,]+)\s+results/', strip_tags($html), $m)) {
            return null;
        }

        $pages = (int) ceil((int) str_replace(',', '', $m[1]) / self::PER_PAGE);
        $cards = self::parseCards($html);

        for ($page = 2; $page <= $pages; $page++) {
            $cards = array_merge($cards, self::parseCards($this->fetchPage($plate, $page)));
        }

        return self::pickTopOperation($cards, $plate);
    }

    /**
     * @param  array<int,array{order_id:?string,parent_id:?string,operation:?string,auto:?string}>  $cards
     */
    public static function pickTopOperation(array $cards, string $plate): ?string
    {
        $perRoot = [];

        foreach ($cards as $card) {
            // filter[search] umumiy qidiruv — raqam haqiqatan "Номер авто"da borligini tekshiramiz
            if (! $card['auto'] || ! str_contains(self::normalizePlate($card['auto']), $plate)) {
                continue;
            }

            $root = $card['parent_id'] ?? $card['order_id'];

            if ($root && $card['operation'] && ! isset($perRoot[$root])) {
                $perRoot[$root] = $card['operation'];
            }
        }

        if (! $perRoot) {
            return null;
        }

        // arsort barqaror: teng sonlarda birinchi uchragani qoladi
        $counts = array_count_values($perRoot);
        arsort($counts);

        return (string) array_key_first($counts);
    }

    /**
     * @return array<int,array{order_id:?string,parent_id:?string,operation:?string,auto:?string}>
     */
    public static function parseCards(string $html): array
    {
        $cards = [];

        (new Crawler($html))
            ->filter('div.w-full.overflow-hidden.rounded-lg.shadow-sm.border')
            ->each(function (Crawler $card) use (&$cards) {
                if (! str_contains($card->text(''), 'Номер авто')) {
                    return;
                }

                $header = $card->children()->first();
                preg_match_all('/#[A-Z]{3}\d{11}/', $header->count() ? $header->text('') : '', $ids);

                // filtr panelida ham OPERATION so'zi bor — shuning uchun faqat karta ichidan qidiriladi
                $cards[] = [
                    'order_id' => $ids[0][0] ?? null,
                    'parent_id' => $ids[0][1] ?? null,
                    'operation' => self::field($card, 'OPERATION'),
                    'auto' => self::field($card, 'Номер авто'),
                ];
            });

        return $cards;
    }

    private static function field(Crawler $card, string $label): ?string
    {
        $value = $card->filterXPath(".//div[normalize-space(.)='{$label}']/following-sibling::*[1]");

        if (! $value->count()) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', $value->first()->text('')));

        return $text !== '' ? $text : null;
    }

    private static function findColumn(array $headers, array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            $index = array_search($candidate, $headers, true);

            if ($index !== false) {
                return $index;
            }
        }

        return null;
    }

    /**
     * `OOO "ABDULLOXON-LOGISTIC"` va `"ASQAROV-IBROHIM" MCHJ` kabi nomlar
     * huquqiy shakl/qo'shtirnoqsiz nomi bo'yicha saralanishi uchun kalit.
     */
    public static function companySortKey(string $company): string
    {
        $key = preg_replace('/(?<![\p{L}\p{N}])(OOO|ООО|MCHJ|МЧЖ|XK|ХК|XF|ХФ|LLC|ЧП|ИП|ТОО|AJ|АЖ)(?![\p{L}\p{N}])/u', ' ', mb_strtoupper($company));
        $key = preg_replace('/[^\p{L}\p{N}\s\'-]/u', ' ', $key);

        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $key)));
    }

    public static function normalizePlate(string $plate): string
    {
        return mb_strtoupper(preg_replace('/[^\p{L}\p{N}]/u', '', $plate));
    }

    private function fetchPage(string $plate, int $page): string
    {
        usleep((int) $this->option('delay') * 1000);

        $response = Http::withOptions(['allow_redirects' => false])
            ->withCookies($this->cookies, 'crm.zanjeer.uz')
            ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
            ->timeout(30)
            ->retry(3, 2000, throw: false)
            ->get(self::ORDERS_URL, [
                'filter' => ['showTrashed' => 0, 'search' => $plate],
                'page' => $page,
            ]);

        // sessiya eskirgan bo'lsa Laravel /login'ga redirect qiladi
        if ($response->redirect() && str_contains((string) $response->header('Location'), '/login')) {
            $this->relogin('CRM sessiyasi eskirgan');

            return $this->fetchPage($plate, $page);
        }

        if (! $response->successful()) {
            throw new RuntimeException("CRM javobi HTTP {$response->status()}");
        }

        return $response->body();
    }

    private function loadCookies(): void
    {
        $token = CrmToken::where('name', 'zanjeer_crm')->first();

        if (! $token || ! $token->session_cookie) {
            $this->relogin('CRM tokenlari topilmadi');

            return;
        }

        $this->cookies = array_filter([
            'XSRF-TOKEN' => $token->xsrf_token,
            $token->session_cookie_name => $token->session_cookie,
        ]);
    }

    /**
     * `--fresh`siz chaqiriladi: u allaqachon ishlab turgan chromedriver'ni
     * chetlab o'tib yangisini ishga tushirishga majburlaydi, serverda esa
     * CHROMEDRIVER_BINARY bo'sh — shuning uchun oddiy `crm:login` ishlatiladi.
     */
    private function relogin(string $reason): void
    {
        if ($this->reloggedIn) {
            $this->sessionBroken = true;

            throw new RuntimeException("{$reason}, qayta login qilingandan keyin ham. \"php artisan crm:login\" ni tekshiring.");
        }

        $this->reloggedIn = true;
        $this->warn("{$reason}, crm:login ishga tushirilmoqda...");

        if (Artisan::call('crm:login') !== self::SUCCESS) {
            $this->sessionBroken = true;

            throw new RuntimeException('crm:login muvaffaqiyatsiz: ' . trim(Artisan::output()));
        }

        $this->loadCookies();
    }
}
