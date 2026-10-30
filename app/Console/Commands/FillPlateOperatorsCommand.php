<?php

namespace App\Console\Commands;

use App\Services\EgsCrmAuthException;
use App\Services\EgsCrmClient;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

/**
 * Excel'dagi har bir mashina raqami bo'yicha EGS CRM buyurtmalarini
 * (API: GET /orders?search=...) olib, ularda eng ko'p qatnashgan
 * OPERATION menejerini oxirgi "Manager" ustuniga yozadi, so'ng qatorlarni
 * kompaniya nomi bo'yicha alifbo tartibida saralab, faylni joyida (yoki
 * --output berilsa o'sha yerga) saqlaydi.
 *
 * Har xil scrape fayllarda ustun nomlari turlicha (Mashina raqami / Plate /
 * Avtomobil raqami / Рег.номер, Company / Company Name), shuning uchun ular
 * avtomatik aniqlanadi. Kompaniya ustuni bo'lmasa (masalan kazakhstan-china),
 * faqat Manager qo'shiladi, saralanmaydi.
 *
 * API har bir sub-buyurtmani alohida qaytaradi; asosiy buyurtma va uning
 * sub-buyurtmalarida operation bir xil, shuning uchun har bir asosiy
 * buyurtma (parent_order_id) bir marta sanaladi. Qidiruv umumiy (yuk nomi,
 * marshrut ham) — faqat avtomobil raqami mos kelganlari olinadi.
 *
 * API kaliti noto'g'ri bo'lsa, qolgan raqamlar so'ralmaydi (Manager bo'sh
 * qoladi), fayl baribir saralanadi.
 */
class FillPlateOperatorsCommand extends Command
{
    protected $signature = 'crm:plate-operators
                            {file : To\'liq yo\'l, masalan storage/app/import_qozoq/qozoq_..._tekshirilgan.xlsx}
                            {--output= : Natijani shu faylga yozish (bo\'sh bo\'lsa asl fayl ustidan yoziladi)}
                            {--plate-column= : Mashina raqami ustuni sarlavhasi (bo\'sh bo\'lsa avtomatik)}
                            {--sort-column= : Saralash ustuni sarlavhasi (bo\'sh bo\'lsa avtomatik)}
                            {--delay=0 : So\'rovlar orasidagi qo\'shimcha pauza, ms (API chegarasi — 120/daqiqa — klientda hisobga olingan)}';

    protected $description = 'Mashina raqami bo\'yicha EGS CRM buyurtmalaridan eng ko\'p OPERATION menejerni topib, "Manager" ustuniga yozadi va Company bo\'yicha saralaydi';

    private const MANAGER_HEADER = 'Manager';

    private const NOT_FOUND = 'topilmadi';

    private const PLATE_HEADERS = ['Mashina raqami', 'Plate', 'Avtomobil raqami', 'Рег.номер'];

    private const COMPANY_HEADERS = ['Company', 'Company Name'];

    /** API kaliti ishlamasa true — qolgan raqamlar uchun so'rov yuborilmaydi */
    private bool $sessionBroken = false;

    public function handle(EgsCrmClient $crm): int
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
                    usleep((int) $this->option('delay') * 1000);
                    $cache[$plate] = self::pickTopOperation(self::cards($crm->orders($plate)), $plate) ?? self::NOT_FOUND;
                } catch (Throwable $e) {
                    $this->error("{$plate}: " . $e->getMessage());
                    $this->sessionBroken = $e instanceof EgsCrmAuthException;

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
            $this->error('CRM API ishlamadi — Manager ustuni to\'liq to\'ldirilmadi.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * API buyurtmalari -> pickTopOperation kartalari.
     *
     * @param  array<int,array<string,mixed>>  $orders
     * @return array<int,array{order_id:?string,parent_id:?string,operation:?string,auto:?string}>
     */
    public static function cards(array $orders): array
    {
        return array_map(fn (array $order) => [
            'order_id' => isset($order['id']) ? (string) $order['id'] : null,
            'parent_id' => isset($order['parent_order_id']) ? (string) $order['parent_order_id'] : null,
            'operation' => trim((string) ($order['operation']['name'] ?? '')) ?: null,
            'auto' => trim((string) ($order['vehicle']['auto_number'] ?? '')) ?: null,
        ], $orders);
    }

    /**
     * @param  array<int,array{order_id:?string,parent_id:?string,operation:?string,auto:?string}>  $cards
     */
    public static function pickTopOperation(array $cards, string $plate): ?string
    {
        $perRoot = [];

        foreach ($cards as $card) {
            // search umumiy qidiruv — raqam haqiqatan avtomobil raqamida borligini tekshiramiz
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
}
