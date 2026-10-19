<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

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

        // Avval bu fayl o'z saytimizdagi /api/upload-qozoq'ga HTTP orqali
        // yuborilardi — SSL sertifikati eskirsa yoki sayt ishlamay qolsa
        // pipeline shu yerda yiqilardi. Endpoint faqat sarlavhani tekshirib,
        // faylni import_qozoq/ ga ko'chirgani uchun, shuni shu yerning o'zida
        // qilamiz.
        $importDir = storage_path('app/import_qozoq');

        if (!file_exists($importDir)) {
            mkdir($importDir, 0777, true);
        }

        $importedQozoqFile = $importDir . '/qozoq_' . now()->format('Y-m-d_H-i-s') . '_tekshirilgan.xlsx';

        file_put_contents($importedQozoqFile, $exportResponse->body());

        $expectedHeaders = ['Chegara nomi', 'Mashina raqami', 'Sana va vaqt', 'Status', 'Company', 'Telefon'];

        try {
            $headers = IOFactory::load($importedQozoqFile)->getActiveSheet()->rangeToArray('A1:F1')[0];
        } catch (\Throwable $e) {
            $headers = null;
        }

        if ($headers !== $expectedHeaders) {
            @unlink($importedQozoqFile);
            Log::error('Warehouse qozoq export formati noto\'g\'ri', ['headers' => $headers]);
            $this->error('Warehouse export fayli formati noto\'g\'ri (sarlavhalar mos emas).');
            return Command::FAILURE;
        }

        $this->info("Tekshirilgan fayl saqlandi: {$importedQozoqFile}");

        // Mashina raqami bo'yicha CRM'dan OPERATION menejerini "Manager"
        // ustuniga yozib, Company bo'yicha saralaydi. Xato bo'lsa fayl
        // menejersiz qoladi, lekin pipeline davom etadi.
        try {
            $this->info('Manager ustuni qo\'shilmoqda (crm:plate-operators)...');
            Artisan::call('crm:plate-operators', ['file' => $importedQozoqFile]);
            $this->info(Artisan::output());
        } catch (\Throwable $e) {
            Log::error('crm:plate-operators muvaffaqiyatsiz', ['error' => $e->getMessage()]);
            $this->warn('Manager ustunini qo\'shishda xato: ' . $e->getMessage());
        }

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

        $this->enrichMergedFile($outputName);

        return Command::SUCCESS;
    }

    /**
     * Birlashtirilgan faylni Zanjeer CRM'dan operator nomi (scrape:zanjeer-operators)
     * va orginfo.uz'dan INN (excel:fill-inn) bilan to'ldirib, alohida yakuniy
     * faylga saqlaydi. Yakuniy fayl storage/app/orginfo/malumotlar_{sana}.xlsx
     * sifatida saqlanadi va `telegram:send-daily-files` buyrug'i orqali
     * kunlik Telegram yuborishlarga qo'shiladi.
     *
     * Muvaffaqiyatsiz bo'lsa ham asosiy pipeline natijasiga (SUCCESS) ta'sir
     * qilmaydi — faqat log'ga yozib, ogohlantirish chiqaradi.
     */
    private function enrichMergedFile(string $mergedFileName): void
    {
        try {
            $this->info('Operator nomlari qo\'shilmoqda (scrape:zanjeer-operators)...');

            Artisan::call('scrape:zanjeer-operators', ['file' => 'merge/' . $mergedFileName]);
            $this->info(Artisan::output());

            $lastOperatorFilePath = storage_path('app/last_operator_file.txt');

            if (!file_exists($lastOperatorFilePath)) {
                throw new \RuntimeException('last_operator_file.txt topilmadi.');
            }

            $operatorsFile = trim(file_get_contents($lastOperatorFilePath));

            if (!$operatorsFile || !file_exists($operatorsFile)) {
                throw new \RuntimeException("Operator fayli topilmadi: {$operatorsFile}");
            }

            $this->info('INN qo\'shilmoqda (excel:fill-inn, orginfo.uz)...');

            $orginfoDir = storage_path('app/orginfo');

            if (!file_exists($orginfoDir)) {
                mkdir($orginfoDir, 0777, true);
            }

            $finalOutput = $orginfoDir . '/malumotlar_' . now()->format('Y-m-d') . '.xlsx';

            Artisan::call('excel:fill-inn', [
                'file' => $operatorsFile,
                'output' => $finalOutput,
            ]);
            $this->info(Artisan::output());

            $this->info("Yakuniy fayl tayyor: {$finalOutput}");
        } catch (\Throwable $e) {
            Log::error('Merged faylni boyitish (operator/INN) muvaffaqiyatsiz', ['error' => $e->getMessage()]);
            $this->warn('Operator/INN qo\'shish bosqichida xato: ' . $e->getMessage());
        }
    }

    private function importAdditionalFiles(string $token, string $baseUrl): void
    {
        $imports = [
            ['endpoint' => 'api/import-qozoq/import', 'file' => $this->latestFile(storage_path('app/qozoq'), 'kazakhstan-uzbekistan-*.xlsx')],
            ['endpoint' => 'api/import-eksport-qozoq/import', 'file' => $this->latestFile(storage_path('app/qozoq'), 'kazakhstan-kyrgyzstan-*.xlsx')],
            ['endpoint' => 'api/import-eksport-qozoq/import', 'file' => $this->latestFile(storage_path('app/qozoq'), 'kazakhstan-china-*.xlsx')],
            ['endpoint' => 'api/turkey/import', 'file' => $this->latestFile(storage_path('app/turkey'), 'turkey_scrape-*.xlsx')],
            ['endpoint' => 'api/belarus-benyakoni/import', 'file' => $this->latestFile(storage_path('app/declarant'), 'benyakoni-*.xlsx')],
            ['endpoint' => 'api/belarus-komennii/import', 'file' => $this->latestFile(storage_path('app/declarant'), 'kamennii-log-*.xlsx')],
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
