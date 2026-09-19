<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QozoqWarehousePipelineCommand extends Command
{
    protected $signature = 'qozoq:warehouse-pipeline';

    protected $description = 'Qoldau Kazakhstan-Russia faylini warehouse orqali tekshirtirib, boshqa scrape fayllar bilan birlashtiradi';

    public function handle()
    {
        set_time_limit(0);

        $sourceFile = $this->latestFile(storage_path('app/qozoq'), 'kazakhstan-russia-*.xlsx');

        if (!$sourceFile) {
            $this->error('Kazakhstan-Russia qozoq fayli topilmadi. Avval "qoldau:scrape \'Kazakhstan - Russia\'" ishga tushirilishi kerak.');
            return Command::FAILURE;
        }

        $baseUrl = rtrim(config('services.warehouse.base_url'), '/');

        $token = $this->warehouseLogin($baseUrl);

        if (!$token) {
            $this->error('Warehouse tizimiga login qilib bo\'lmadi.');
            return Command::FAILURE;
        }

        $this->info("Import qilinmoqda: {$sourceFile}");

        $importResponse = Http::withToken($token)
            ->timeout(120)
            ->attach('file', file_get_contents($sourceFile), basename($sourceFile))
            ->post("{$baseUrl}/api/qozoq/import");

        if (!$importResponse->successful()) {
            Log::error('Warehouse qozoq import failed', ['response' => $importResponse->body()]);
            $this->error('Warehouse import xato: ' . $importResponse->body());
            return Command::FAILURE;
        }

        $this->importAdditionalFiles($token, $baseUrl);

        $this->info('Import muvaffaqiyatli. Export qilinmoqda...');

        $exportResponse = Http::withToken($token)
            ->timeout(120)
            ->get("{$baseUrl}/api/qozoq/export", [
                'date' => now()->format('Y-m-d'),
            ]);

        if (!$exportResponse->successful()) {
            Log::error('Warehouse qozoq export failed', ['status' => $exportResponse->status()]);
            $this->error('Warehouse export xato: HTTP ' . $exportResponse->status());
            return Command::FAILURE;
        }

        $checkedFileName = 'qozoq_' . now()->format('Y-m-d_H-i-s') . '_tekshirilgan.xlsx';
        $tmpDir = storage_path('app/tmp');

        if (!file_exists($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        $checkedTmpPath = $tmpDir . '/' . $checkedFileName;

        file_put_contents($checkedTmpPath, $exportResponse->body());

        $this->info("Tekshirilgan fayl yuklab olindi: {$checkedTmpPath}");

        $uploadResponse = Http::timeout(60)
            ->attach('excel', file_get_contents($checkedTmpPath), $checkedFileName)
            ->post(rtrim(config('app.url'), '/') . '/api/upload-qozoq');

        @unlink($checkedTmpPath);

        if (!$uploadResponse->successful()) {
            Log::error('upload-qozoq failed', ['response' => $uploadResponse->body()]);
            $this->error('upload-qozoq xato: ' . $uploadResponse->body());
            return Command::FAILURE;
        }

        $uploadedFileName = $uploadResponse->json('file_name');
        $importedQozoqFile = storage_path('app/import_qozoq/' . $uploadedFileName);

        $this->info("Tekshirilgan fayl saqlandi: {$importedQozoqFile}");

        $mergeFiles = array_values(array_filter([
            $importedQozoqFile,
            $this->latestFile(storage_path('app/declarant'), 'benyakoni-*.xlsx'),
            $this->latestFile(storage_path('app/declarant'), 'kamennii-log-*.xlsx'),
            $this->latestFile(storage_path('app/declarant'), 'kozlovichi-*.xlsx'),
            $this->latestFile(storage_path('app/zitic'), 'zitic_*.xlsx'),
            $this->latestFile(storage_path('app/turkey'), 'turkey_scrape-*.xlsx'),
        ]));

        if (count($mergeFiles) < 2) {
            $this->warn('Merge uchun fayllar yetarli emas, faqat topilganlar bilan davom etiladi.');
        }

        $outputName = 'merged-' . now()->format('Y-m-d_H-i-s') . '.xlsx';

        $this->info('Fayllar birlashtirilmoqda: ' . implode(', ', $mergeFiles));

        Artisan::call('excel:normalize', [
            'output' => $outputName,
            'files' => $mergeFiles,
        ]);

        $this->info(Artisan::output());

        $this->info('Pipeline tugadi: ' . storage_path('app/merge/' . $outputName));

        return Command::SUCCESS;
    }

    private function importAdditionalFiles(string $token, string $baseUrl): void
    {
        $imports = [
            ['endpoint' => 'api/import-qozoq/import', 'file' => $this->latestFile(storage_path('app/qozoq'), 'kazakhstan-uzbekistan-*.xlsx')],
            ['endpoint' => 'api/import-eksport-qozoq/import', 'file' => $this->latestFile(storage_path('app/qozoq'), 'kazakhstan-kyrgyzstan-*.xlsx')],
            ['endpoint' => 'api/import-eksport-qozoq/import', 'file' => $this->latestFile(storage_path('app/qozoq'), 'kazakhstan-china-*.xlsx')],
            ['endpoint' => 'api/turkey/import', 'file' => $this->latestFile(storage_path('app/turkey'), 'turkey_scrape-*.xlsx')],
            ['endpoint' => 'api/belarus-benyakoni/import', 'file' => $this->latestFile(storage_path('app/declarant'), 'benyakoni-*.xlsx')],
            ['endpoint' => 'api/belarus-komenii/import', 'file' => $this->latestFile(storage_path('app/declarant'), 'kamennii-log-*.xlsx')],
        ];

        foreach ($imports as $item) {
            if (!$item['file']) {
                $this->warn("Fayl topilmadi, o'tkazib yuborildi: {$item['endpoint']}");
                continue;
            }

            $this->uploadImport($token, $baseUrl, $item['endpoint'], $item['file']);
        }
    }

    private function uploadImport(string $token, string $baseUrl, string $endpoint, string $filePath): bool
    {
        $this->info("Import qilinmoqda: {$filePath} -> {$endpoint}");

        $response = Http::withToken($token)
            ->timeout(120)
            ->attach('file', file_get_contents($filePath), basename($filePath))
            ->post("{$baseUrl}/{$endpoint}");

        if (!$response->successful()) {
            Log::error('Warehouse import failed', ['endpoint' => $endpoint, 'file' => $filePath, 'response' => $response->body()]);
            $this->error("Warehouse import xato ({$endpoint}): " . $response->body());
            return false;
        }

        return true;
    }

    private function warehouseLogin(string $baseUrl): ?string
    {
        $response = Http::timeout(30)->post("{$baseUrl}/api/login", [
            'email' => config('services.warehouse.email'),
            'password' => config('services.warehouse.password'),
        ]);

        if (!$response->successful()) {
            Log::error('Warehouse login failed', ['response' => $response->body()]);
            return null;
        }

        return $response->json('token');
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
}
