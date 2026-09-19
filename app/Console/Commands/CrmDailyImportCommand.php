<?php

namespace App\Console\Commands;

use App\Support\Crm\CrmSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;
use ZipArchive;

/**
 * Kunlik scrape natijalarini (Qoldau Kazakhstan-Russia, Turkey, Zitic,
 * Belarus declarant) tegishli format buyruqlari orqali tayyorlab, bitta
 * CRM login sessiyasida ketma-ket Zanjeer CRM'ning "Границы -> Торнадо"
 * (custom-operations) importiga yuklaydi.
 *
 * Har bir bosqich mustaqil ravishda try/catch qilinadi — biri
 * muvaffaqiyatsiz bo'lsa ham, qolganlari davom etadi. Oxirida har bir
 * granitsa bo'yicha natija jadval ko'rinishida chiqariladi.
 */
class CrmDailyImportCommand extends Command
{
    protected $signature = 'crm:daily-import
                            {--email= : .env dagi CRM_LOGIN_EMAIL o\'rniga ishlatiladi}
                            {--password= : .env dagi CRM_LOGIN_PASSWORD o\'rniga ishlatiladi}
                            {--timeout=60 : Har bir bosqich uchun kutish vaqti (soniya)}';

    protected $description = 'Qoldau/Turkey/Zitic/Declarant fayllarini formatlab, bitta CRM sessiyasida Zanjeer import qiladi';

    private array $results = [];

    public function handle(): int
    {
        set_time_limit(0);

        $config = config('crm.zanjeer');

        $email = $this->option('email') ?: $config['email'];
        $password = $this->option('password') ?: $config['password'];

        if (! $email || ! $password) {
            $this->error('CRM_LOGIN_EMAIL va CRM_LOGIN_PASSWORD .env faylida (yoki --email= / --password= bilan) berilishi shart.');

            return self::FAILURE;
        }

        $timeout = (int) $this->option('timeout');
        $session = null;

        try {
            $this->info('chromedriver va brauzer tayyorlanmoqda...');
            $session = CrmSession::start($config);

            $this->info('Tizimga kirilmoqda...');
            $session->login($config, $email, $password, $timeout);
        } catch (Throwable $e) {
            $this->error('CRM ga kirib bo\'lmadi: ' . $e->getMessage());
            $session?->quit();

            return self::FAILURE;
        }

        try {
            $this->importKazakhstanRussia($session, $config, $timeout);
            $this->importTurkey($session, $config, $timeout);
            $this->importZitic($session, $config, $timeout);
            $this->importDeclarant($session, $config, $timeout);
        } finally {
            $session->quit();
        }

        $this->printSummary();

        $hasFailure = collect($this->results)->contains(fn ($r) => $r['status'] !== 'ok');

        return $hasFailure ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Qoldau "Kazakhstan - Russia" faylini granitsalar bo'yicha bo'lib
     * (excel:split), har bir granitsani alohida import qiladi.
     */
    private function importKazakhstanRussia(CrmSession $session, array $config, int $timeout): void
    {
        $this->line('=== Kazakhstan - Russia (excel:split) ===');

        $sourceFile = $this->latestFile(storage_path('app/qozoq'), 'kazakhstan-russia-*.xlsx');

        if (! $sourceFile) {
            $this->warn('Kazakhstan-Russia qozoq fayli topilmadi, o\'tkazib yuborildi.');
            $this->results[] = ['group' => 'Kazakhstan - Russia', 'border' => '-', 'status' => 'skip', 'message' => 'Manba fayl topilmadi'];

            return;
        }

        $zipPath = storage_path('app/exports/' . pathinfo($sourceFile, PATHINFO_FILENAME) . '.zip');

        Artisan::call('excel:split', ['file' => $sourceFile]);
        $this->line(Artisan::output());

        if (! file_exists($zipPath)) {
            $this->error("Split zip topilmadi: {$zipPath}");
            $this->results[] = ['group' => 'Kazakhstan - Russia', 'border' => '-', 'status' => 'error', 'message' => 'ZIP yaratilmadi'];

            return;
        }

        $extractDir = storage_path('app/tmp/split-' . now()->format('Y-m-d_H-i-s'));
        mkdir($extractDir, 0777, true);

        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            $this->error("ZIP ochilmadi: {$zipPath}");
            $this->results[] = ['group' => 'Kazakhstan - Russia', 'border' => '-', 'status' => 'error', 'message' => 'ZIP ochilmadi'];

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

            $this->importOne('Kazakhstan - Russia', $border, $file, $session, $config, $timeout);
        }

        $this->deleteDirectory($extractDir);
    }

    /**
     * Turkey scrape faylini import formatiga o'tkazib (excel:border-convert),
     * "Хопа-Сарпи" granitsasiga yuklaydi.
     */
    private function importTurkey(CrmSession $session, array $config, int $timeout): void
    {
        $this->line('=== Turkey (excel:border-convert) ===');

        $sourceFile = $this->latestFile(storage_path('app/turkey'), 'turkey_scrape-*.xlsx');

        if (! $sourceFile) {
            $this->warn('Turkey scrape fayli topilmadi, o\'tkazib yuborildi.');
            $this->results[] = ['group' => 'Turkey', 'border' => 'Хопа-Сарпи', 'status' => 'skip', 'message' => 'Manba fayl topilmadi'];

            return;
        }

        $converted = $this->tmpPath('turkey_converted');

        Artisan::call('excel:border-convert', ['input' => $sourceFile, 'output' => $converted]);
        $this->line(Artisan::output());

        $this->importOne('Turkey', 'Хопа-Сарпи', $converted, $session, $config, $timeout);

        @unlink($converted);
    }

    /**
     * Zitic scrape faylini import formatiga o'tkazib (zitic:convert),
     * "Владикавказ — Казбеги" granitsasiga yuklaydi.
     */
    private function importZitic(CrmSession $session, array $config, int $timeout): void
    {
        $this->line('=== Zitic (zitic:convert) ===');

        $sourceFile = $this->latestFile(storage_path('app/zitic'), 'zitic_*.xlsx');

        if (! $sourceFile) {
            $this->warn('Zitic scrape fayli topilmadi, o\'tkazib yuborildi.');
            $this->results[] = ['group' => 'Zitic', 'border' => 'Владикавказ — Казбеги', 'status' => 'skip', 'message' => 'Manba fayl topilmadi'];

            return;
        }

        $converted = $this->tmpPath('zitic_converted');

        Artisan::call('zitic:convert', ['input' => $sourceFile, 'output' => $converted]);
        $this->line(Artisan::output());

        $this->importOne('Zitic', 'Владикавказ — Казбеги', $converted, $session, $config, $timeout);

        @unlink($converted);
    }

    /**
     * Belarus declarant fayllarini (benyakoni, kamennii-log) import
     * formatiga o'tkazib (declarant:convert), tegishli granitsalarga
     * yuklaydi.
     */
    private function importDeclarant(CrmSession $session, array $config, int $timeout): void
    {
        $this->line('=== Declarant (declarant:convert) ===');

        $zones = [
            'benyakoni' => 'Бенякони - Шальчининкай',
            'kamennii-log' => 'Каменный Лог - Мядининкай',
        ];

        foreach ($zones as $zone => $border) {
            $sourceFile = $this->latestFile(storage_path('app/declarant'), $zone . '-*.xlsx');

            if (! $sourceFile) {
                $this->warn("{$zone} fayli topilmadi, o'tkazib yuborildi.");
                $this->results[] = ['group' => 'Declarant', 'border' => $border, 'status' => 'skip', 'message' => 'Manba fayl topilmadi'];

                continue;
            }

            $converted = $this->tmpPath($zone . '_converted');

            Artisan::call('declarant:convert', ['input' => $sourceFile, 'output' => $converted]);
            $this->line(Artisan::output());

            $this->importOne('Declarant', $border, $converted, $session, $config, $timeout);

            @unlink($converted);
        }
    }

    private function importOne(string $group, string $border, string $file, CrmSession $session, array $config, int $timeout): void
    {
        if (! file_exists($file)) {
            $this->error("Fayl topilmadi: {$file}");
            $this->results[] = ['group' => $group, 'border' => $border, 'status' => 'error', 'message' => 'Konvert qilingan fayl topilmadi'];

            return;
        }

        try {
            $this->info("Import qilinmoqda: {$border} <= {$file}");

            $session->importFile($config, $border, $file, $timeout);

            $this->info("OK: {$border}");
            $this->results[] = ['group' => $group, 'border' => $border, 'status' => 'ok', 'message' => 'Muvaffaqiyatli'];
        } catch (Throwable $e) {
            Log::error('CRM import failed', ['border' => $border, 'file' => $file, 'error' => $e->getMessage()]);
            $this->error("XATO ({$border}): " . $e->getMessage());
            $this->results[] = ['group' => $group, 'border' => $border, 'status' => 'error', 'message' => $e->getMessage()];
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

    private function tmpPath(string $prefix): string
    {
        $dir = storage_path('app/tmp');

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir . '/' . $prefix . '_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
    }

    private function latestFile(string $directory, string $pattern): ?string
    {
        $files = glob($directory . '/' . $pattern);

        if (empty($files)) {
            return null;
        }

        usort($files, fn ($a, $b) => filemtime($b) - filemtime($a));

        return $files[0];
    }

    private function deleteDirectory(string $dir): void
    {
        foreach (glob($dir . '/*') as $file) {
            @unlink($file);
        }

        @rmdir($dir);
    }
}
