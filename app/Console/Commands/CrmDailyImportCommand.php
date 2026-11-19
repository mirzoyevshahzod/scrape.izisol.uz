<?php

namespace App\Console\Commands;

use App\Services\EgsCrmClient;
use App\Support\DailyFiles;
use App\Support\Telegram\TelegramSender;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;
use ZipArchive;

/**
 * Kunlik scrape natijalarini (Qoldau Kazakhstan-Russia, Turkey, Zitic,
 * Belarus declarant) tegishli format buyruqlari orqali tayyorlab, EGS CRM
 * API'si orqali "Границы -> Торнадо" (custom-operations) importiga yuklaydi
 * (EgsCrmClient). Granitsa nomi API'dagi ro'yxatdan ID'ga aylantiriladi.
 *
 * Har bir bosqich mustaqil ravishda try/catch qilinadi — biri
 * muvaffaqiyatsiz bo'lsa ham, qolganlari davom etadi. Oxirida har bir
 * granitsa bo'yicha natija jadval ko'rinishida chiqariladi.
 */
class CrmDailyImportCommand extends Command
{
    protected $signature = 'crm:daily-import
                            {--only= : Faqat shu bosqich(lar)ni ishga tushirish, vergul bilan ajratib (masalan: zitic,kazakhstan-russia). Bo\'sh bo\'lsa hammasi ishga tushadi}
                            {--date= : Shu kun (YYYY-MM-DD) fayllarini import qilish. Bo\'sh bo\'lsa — eng oxirgi fayllar}
                            {--notify : Natijani Telegram\'ga hisobot qilib yuborish (soatlik jadval shu bilan ishga tushiradi)}';

    protected $description = 'Qoldau/Turkey/Zitic/Declarant fayllarini formatlab, EGS CRM API orqali "Торнадо"ga import qiladi';

    private array $results = [];

    /** Hozir ishlanayotgan bosqichning manba fayli (hisobotda fayl qachon yangilangani ko'rinsin) */
    private ?string $currentSource = null;

    /** Manba fayl shundan eski bo'lsa (soat), hisobotda "eski" deb belgilanadi — scraping yangilamagan */
    private const STALE_HOURS = 2;

    /** --date qiymati (null — eng oxirgi fayllar) */
    private ?string $date = null;

    public function handle(EgsCrmClient $crm): int
    {
        set_time_limit(0);

        $startedAt = now();
        $fatal = null;
        $exitCode = $this->runImport($crm, $fatal);

        if ($this->option('notify')) {
            $this->notify($startedAt, $fatal);
        }

        return $exitCode;
    }

    /**
     * @param  string|null  $fatal  butun import to'xtagan bo'lsa — sababi (hisobot uchun)
     */
    private function runImport(EgsCrmClient $crm, ?string &$fatal): int
    {
        try {
            $this->date = DailyFiles::parseDateOption($this->option('date'));
        } catch (\InvalidArgumentException $e) {
            $this->error($fatal = $e->getMessage());

            return self::FAILURE;
        }

        if ($this->date) {
            $this->info("Sana: {$this->date}");
        }

        $allLabels = ['Kazakhstan - Russia', 'Turkey', 'Zitic', 'Declarant'];
        $wantedLabels = $this->resolveOnlyLabels($allLabels, $this->option('only'));

        if ($wantedLabels === []) {
            $this->error($fatal = "--only qiymati mos kelmadi. Mavjud bosqichlar: " . implode(', ', $allLabels));

            return self::FAILURE;
        }

        try {
            $this->info('CRM API: granitsalar ro\'yxati olinmoqda...');
            $this->info('Granitsalar: ' . count($crm->borders()) . ' ta');
        } catch (Throwable $e) {
            $this->error($fatal = 'CRM API ishlamayapti: ' . $e->getMessage());

            return self::FAILURE;
        }

        $pipelines = array_intersect_key([
            'Kazakhstan - Russia' => fn () => $this->importKazakhstanRussia($crm),
            'Turkey' => fn () => $this->importTurkey($crm),
            'Zitic' => fn () => $this->importZitic($crm),
            'Declarant' => fn () => $this->importDeclarant($crm),
        ], array_flip($wantedLabels));

        foreach ($pipelines as $label => $pipeline) {
            $this->currentSource = null;

            try {
                $pipeline();
            } catch (Throwable $e) {
                // Kutilmagan xato (masalan konvert bosqichidagi bug) bitta
                // bosqichni to'xtatsin, lekin qolgan bosqichlar davom etsin.
                Log::error('CRM daily-import pipeline failed', ['pipeline' => $label, 'error' => $e->getMessage()]);
                $this->error("XATO ({$label}): " . $e->getMessage());
                $this->results[] = $this->withSource(['group' => $label, 'border' => '-', 'status' => 'error', 'message' => $e->getMessage()]);
            }
        }

        $this->printSummary();

        $hasFailure = collect($this->results)->contains(fn ($r) => $r['status'] !== 'ok');

        return $hasFailure ? self::FAILURE : self::SUCCESS;
    }

    /**
     * `--only=` orqali berilgan nomlarga mos bosqich nomlarini
     * (asl `$allLabels`dan) qaytaradi (masalan
     * `--only=zitic,kazakhstan-russia` faqat shu ikkitasini tanlaydi).
     * Solishtirish kichik harfli, bo'sh joysiz shaklda bo'ladi —
     * shuning uchun "zitic", "Zitic", "kazakhstan-russia" barchasi
     * ishlaydi. `--only` berilmasa, barcha nomlar qaytadi. Hech biri
     * mos kelmasa, bo'sh massiv qaytadi (chaqiruvchi shu holatni xato
     * deb hisoblaydi).
     *
     * @param array<int,string> $allLabels
     * @return array<int,string>
     */
    private function resolveOnlyLabels(array $allLabels, ?string $only): array
    {
        if (! $only) {
            return $allLabels;
        }

        $normalize = fn ($name) => strtolower(str_replace(' ', '', trim($name)));

        $wanted = array_map($normalize, explode(',', $only));

        return array_values(array_filter(
            $allLabels,
            fn ($label) => in_array($normalize($label), $wanted, true)
        ));
    }

    /**
     * Qoldau "Kazakhstan - Russia" faylini granitsalar bo'yicha bo'lib
     * (excel:split), har bir granitsani alohida import qiladi.
     */
    private function importKazakhstanRussia(EgsCrmClient $crm): void
    {
        $this->line('=== Kazakhstan - Russia (excel:split) ===');

        $sourceFile = DailyFiles::latest(storage_path('app/qozoq'), 'kazakhstan-russia-*.xlsx', $this->date);
        $this->currentSource = $sourceFile;

        if (! $sourceFile) {
            $this->warn('Kazakhstan-Russia qozoq fayli topilmadi, o\'tkazib yuborildi.');
            $this->results[] = $this->withSource(['group' => 'Kazakhstan - Russia', 'border' => '-', 'status' => 'skip', 'message' => 'Manba fayl topilmadi' . ($this->date ? " ({$this->date})" : '')]);

            return;
        }

        $zipPath = storage_path('app/exports/' . pathinfo($sourceFile, PATHINFO_FILENAME) . '.zip');

        Artisan::call('excel:split', ['file' => $sourceFile]);
        $this->line(Artisan::output());

        if (! file_exists($zipPath)) {
            $this->error("Split zip topilmadi: {$zipPath}");
            $this->results[] = $this->withSource(['group' => 'Kazakhstan - Russia', 'border' => '-', 'status' => 'error', 'message' => 'ZIP yaratilmadi']);

            return;
        }

        $extractDir = storage_path('app/tmp/split-' . now()->format('Y-m-d_H-i-s'));
        mkdir($extractDir, 0777, true);

        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            $this->error("ZIP ochilmadi: {$zipPath}");
            $this->results[] = $this->withSource(['group' => 'Kazakhstan - Russia', 'border' => '-', 'status' => 'error', 'message' => 'ZIP ochilmadi']);

            return;
        }

        $zip->extractTo($extractDir);
        $zip->close();

        $files = glob($extractDir . '/*.xlsx');

        if (empty($files)) {
            $this->warn('ZIP ichida fayl topilmadi.');
        }

        foreach ($files as $file) {
            $border = preg_replace('/_\d{4}-\d{2}-\d{2}$/', '', pathinfo($file, PATHINFO_FILENAME));

            $this->importOne('Kazakhstan - Russia', $border, $file, $crm);
        }

        $this->deleteDirectory($extractDir);
    }

    /**
     * Turkey scrape faylini import formatiga o'tkazib (excel:border-convert),
     * "Хопа-Сарпи" granitsasiga yuklaydi.
     */
    private function importTurkey(EgsCrmClient $crm): void
    {
        $this->line('=== Turkey (excel:border-convert) ===');

        $sourceFile = DailyFiles::latest(storage_path('app/turkey'), 'turkey_scrape-*.xlsx', $this->date);
        $this->currentSource = $sourceFile;

        if (! $sourceFile) {
            $this->warn('Turkey scrape fayli topilmadi, o\'tkazib yuborildi.');
            $this->results[] = $this->withSource(['group' => 'Turkey', 'border' => 'Хопа-Сарпи', 'status' => 'skip', 'message' => 'Manba fayl topilmadi' . ($this->date ? " ({$this->date})" : '')]);

            return;
        }

        $converted = $this->tmpPath('turkey_converted');

        Artisan::call('excel:border-convert', ['input' => $sourceFile, 'output' => $converted]);
        $this->line(Artisan::output());

        $this->importOne('Turkey', 'Хопа-Сарпи', $converted, $crm);

        @unlink($converted);
    }

    /**
     * Zitic scrape faylini import formatiga o'tkazib (zitic:convert),
     * "Владикавказ — Казбеги" granitsasiga yuklaydi.
     */
    private function importZitic(EgsCrmClient $crm): void
    {
        $this->line('=== Zitic (zitic:convert) ===');

        $sourceFile = DailyFiles::latest(storage_path('app/zitic'), 'zitic_*.xlsx', $this->date);
        $this->currentSource = $sourceFile;

        if (! $sourceFile) {
            $this->warn('Zitic scrape fayli topilmadi, o\'tkazib yuborildi.');
            $this->results[] = $this->withSource(['group' => 'Zitic', 'border' => 'Владикавказ — Казбеги', 'status' => 'skip', 'message' => 'Manba fayl topilmadi' . ($this->date ? " ({$this->date})" : '')]);

            return;
        }

        $converted = $this->tmpPath('zitic_converted');

        Artisan::call('zitic:convert', ['input' => $sourceFile, 'output' => $converted]);
        $this->line(Artisan::output());

        $this->importOne('Zitic', 'Владикавказ — Казбеги', $converted, $crm);

        @unlink($converted);
    }

    /**
     * Belarus declarant fayllarini (benyakoni, kamennii-log) import
     * formatiga o'tkazib (declarant:convert), tegishli granitsalarga
     * yuklaydi.
     */
    private function importDeclarant(EgsCrmClient $crm): void
    {
        $this->line('=== Declarant (declarant:convert) ===');

        $zones = [
            'benyakoni' => 'Бенякони - Шальчининкай',
            'kamennii-log' => 'Каменный Лог - Мядининкай',
        ];

        foreach ($zones as $zone => $border) {
            try {
                $sourceFile = DailyFiles::latest(storage_path('app/declarant'), $zone . '-*.xlsx', $this->date);
                $this->currentSource = $sourceFile;

                if (! $sourceFile) {
                    $this->warn("{$zone} fayli topilmadi, o'tkazib yuborildi.");
                    $this->results[] = $this->withSource(['group' => 'Declarant', 'border' => $border, 'status' => 'skip', 'message' => 'Manba fayl topilmadi' . ($this->date ? " ({$this->date})" : '')]);

                    continue;
                }

                $converted = $this->tmpPath($zone . '_converted');

                Artisan::call('declarant:convert', ['input' => $sourceFile, 'output' => $converted]);
                $this->line(Artisan::output());

                $this->importOne('Declarant', $border, $converted, $crm);

                @unlink($converted);
            } catch (Throwable $e) {
                // Bitta zona (masalan benyakoni) konvert bosqichida qulasa
                // ham, ikkinchisi (kamennii-log) baribir sinab ko'rilsin.
                Log::error('Declarant convert/import failed', ['zone' => $zone, 'error' => $e->getMessage()]);
                $this->error("XATO ({$border}) konvert/import bosqichida: " . $e->getMessage());
                $this->results[] = $this->withSource(['group' => 'Declarant', 'border' => $border, 'status' => 'error', 'message' => $e->getMessage()]);
            }
        }
    }

    private function importOne(string $group, string $border, string $file, EgsCrmClient $crm): void
    {
        if (! file_exists($file)) {
            $this->error("Fayl topilmadi: {$file}");
            $this->results[] = $this->withSource(['group' => $group, 'border' => $border, 'status' => 'error', 'message' => 'Konvert qilingan fayl topilmadi']);

            return;
        }

        try {
            $borderId = $crm->borderId($border);

            if ($borderId === null) {
                throw new \RuntimeException("'{$border}' granitsasi CRM'da topilmadi. Mavjudlari: "
                    . implode(', ', array_column($crm->borders(), 'name')));
            }

            $this->info("Import qilinmoqda: {$border} (ID {$borderId}) <= {$file}");

            $rows = $crm->importOperations($borderId, $file);

            $this->info("OK: {$border} — {$rows} ta qator");
            $this->results[] = $this->withSource(['group' => $group, 'border' => $border, 'status' => 'ok', 'message' => "{$rows} ta qator yuklandi", 'rows' => (int) $rows]);
        } catch (Throwable $e) {
            Log::error('CRM import failed', ['border' => $border, 'file' => $file, 'error' => $e->getMessage()]);
            $this->error("XATO ({$border}): " . $e->getMessage());
            $this->results[] = $this->withSource(['group' => $group, 'border' => $border, 'status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    private function printSummary(): void
    {
        $this->line('');
        $this->line('=== Natijalar ===');

        $this->table(
            ['Guruh', 'Granitsa', 'Holat', 'Izoh'],
            array_map(fn ($r) => [$r['group'], $r['border'], strtoupper($r['status']), $r['message']], $this->results)
        );
    }

    /** Natija qatoriga shu bosqichning manba faylini qo'shadi */
    private function withSource(array $result): array
    {
        return $result + ['source' => $this->currentSource];
    }

    /**
     * Har soatlik import natijasini Telegram'ga yuboradi: har bir granitsa
     * holati va manba fayl qachon yangilangani (scraping ishlayaptimi —
     * shundan ko'rinadi). Telegram ishlamasa ham import natijasi o'zgarmaydi.
     */
    private function notify(Carbon $startedAt, ?string $fatal): void
    {
        $chatId = config('services.telegram.report_chat_id');

        if (! $chatId || ! config('services.telegram.daily_files_bot_token')) {
            $this->warn('Telegram hisobot yuborilmadi: TELEGRAM_SCRAPING_BOT_TOKEN yoki TELEGRAM_REPORT_CHAT_ID yo\'q.');

            return;
        }

        $sent = (new TelegramSender())->sendMessage($chatId, $this->report($startedAt, $fatal), [
            'parse_mode' => 'HTML',
            'link_preview_options' => ['is_disabled' => true],
        ]);

        $sent ? $this->info('Telegram hisobot yuborildi.') : $this->warn('Telegram hisobot yuborilmadi (Telegram javob bermadi).');
    }

    private function report(Carbon $startedAt, ?string $fatal = null): string
    {
        $esc = fn (?string $text) => htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE);
        $short = fn (string $text) => mb_strlen($text) > 200 ? mb_substr($text, 0, 200) . '…' : $text;
        $minutes = (int) ceil($startedAt->diffInSeconds(now()) / 60);
        $time = $startedAt->format('d.m.Y H:i');

        if ($fatal !== null) {
            return "❌ <b>CRM import — {$time}</b>\nImport bajarilmadi: " . $esc($short($fatal));
        }

        $errors = collect($this->results)->where('status', 'error')->count();
        $ok = collect($this->results)->where('status', 'ok');
        $stale = false;
        $lines = [];

        foreach (collect($this->results)->groupBy('group') as $group => $rows) {
            $sources = $rows->pluck('source')->filter()->unique();
            $header = '<b>' . $esc($group) . '</b>';

            if ($sources->count() === 1) {
                [$label, $isStale] = $this->sourceLabel($sources->first());
                $header .= " · {$label}";
                $stale = $stale || $isStale;
            }

            $lines[] = '';
            $lines[] = $header;

            foreach ($rows as $row) {
                $icon = ['ok' => '✅', 'skip' => '⏭', 'error' => '❌'][$row['status']] ?? '•';
                $line = "{$icon} " . ($row['border'] !== '-' ? $esc($row['border']) . ': ' : '') . $esc($short($row['message']));

                if ($sources->count() > 1 && $row['source']) {
                    [$label, $isStale] = $this->sourceLabel($row['source']);
                    $line .= " · {$label}";
                    $stale = $stale || $isStale;
                }

                $lines[] = $line;
            }
        }

        $icon = $errors ? '❌' : ($stale || collect($this->results)->contains('status', 'skip') ? '⚠️' : '✅');
        $summary = "Yuklandi: {$ok->count()} ta granitsa, "
            . $ok->sum('rows') . ' ta qator'
            . ($errors ? "; xato: {$errors} ta" : '')
            . " · ⏱ {$minutes} daq";

        $text = "{$icon} <b>CRM import — {$time}</b>\n{$summary}\n" . implode("\n", $lines);

        if ($stale) {
            $text .= "\n\n⚠️ Eski fayl — shu bosqich scrapingi oxirgi " . self::STALE_HOURS . " soatda yangi fayl yaratmagan, CRM'ga eski ma'lumot yuklandi.";
        }

        return mb_strlen($text) > 4000 ? mb_substr($text, 0, 4000) . '…' : $text;
    }

    /** @return array{string,bool} [matn, eskimi] */
    private function sourceLabel(string $file): array
    {
        if (! is_file($file)) {
            return ['fayl yo\'q', true];
        }

        $modified = Carbon::createFromTimestamp(filemtime($file), config('app.timezone'));
        $stale = $modified->lt(now()->subHours(self::STALE_HOURS));
        $label = 'fayl ' . $modified->format($modified->isToday() ? 'H:i' : 'd.m H:i');

        return $stale ? ["⚠️ {$label} (eski)", true] : [$label, false];
    }

    private function tmpPath(string $prefix): string
    {
        $dir = storage_path('app/tmp');

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir . '/' . $prefix . '_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
    }

    private function deleteDirectory(string $dir): void
    {
        foreach (glob($dir . '/*') as $file) {
            @unlink($file);
        }

        @rmdir($dir);
    }
}
