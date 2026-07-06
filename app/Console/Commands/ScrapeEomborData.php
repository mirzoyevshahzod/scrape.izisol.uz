<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Exception;

class ScrapeEomborData extends Command
{
    protected $signature = 'scrape:eombor {start_id} {count} {end_id}';
    protected $description = 'Scrape e-ombor via API (no selenium)';

    public function handle()
    {
        $this->info('🚀 Starting API scraping...');

        // 🔑 O'ZINGNI COOKIE
        $session = 'JSESSIONID=2C59F8154A22936FBA9C0EAE7C7AE469';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $rowIndex = 1;

        $startTransitId = $this->argument('start_id');
        $count = (int)$this->argument('count');
        $endTransitId = $this->argument('end_id');

        $filePath = storage_path("app/e-ombor-{$startTransitId}-{$endTransitId}.xlsx");

        try {

            // Header yozish
            $headings = [
                'Document Number',
                'Custom Code',
                'Custom Date',
                'TEBHN Number',
                'Transport Number',
                'Gross Weight',
                'INN',
                'Recipient Name',
                'Delivery Post',
                'Delivery Date',
                'Arrival Place',
                'Status'
            ];

            foreach ($headings as $colIndex => $heading) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                $sheet->setCellValue($columnLetter . $rowIndex, $heading);
            }

            $rowIndex++;

            $this->info("📊 Scraping from {$startTransitId}...");

            for ($j = 0; $j < $count; $j++) {

                $currentTransitId = $this->generateNextTransitId($startTransitId, $j);
                $this->info("🔍 {$currentTransitId}");

                try {

                    // 🔥 API REQUEST
                    $response = Http::withHeaders([
                        'Cookie' => $session,
                        'User-Agent' => 'Mozilla/5.0',
                        'Referer' => 'https://e-ombor.customs.uz/',
                        'Accept' => 'text/html',
                    ])->asForm()->post(
                        'https://e-ombor.customs.uz/tifPages/monitoringEtranzit/obrabotkaFilter/monitoringEtranzitObrabotka.jsp',
                        [
                            'ATRW' => $currentTransitId,
                            'grafa7' => '',
                            'transport_turi' => 'avto',
                            'transport_raqami' => ''
                        ]
                    );

                    if (!$response->ok()) {
                        $this->warn("❌ Request failed");
                        continue;
                    }

                    $html = $response->body();

                    // ❗ Ma’lumot yo‘qligini tekshirish
                    if (str_contains($html, 'Маълумот топилмади') || str_contains($html, 'Жадвал бўш')) {
                        $this->warn("⚠️ No data");
                        continue;
                    }

                    // HTML parse
                    preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $html, $matches);

                    if (empty($matches[1])) {
                        $this->warn("⚠️ Empty table");
                        continue;
                    }

                    $cells = array_map(function ($v) {
                        return trim(strip_tags($v));
                    }, $matches[1]);

                    if (count($cells) < 10) {
                        $this->warn("⚠️ Invalid response");
                        continue;
                    }

                    // Mapping
                    $docNumber = $cells[0] ?? '';
                    $customCode = $cells[1] ?? '';
                    $customDate = $cells[2] ?? '';
                    $tebhn = $cells[3] ?? '';
                    $transport = $cells[4] ?? '';
                    $weight = $cells[5] ?? '';
                    $recipient = $cells[6] ?? '';
                    $deliveryPost = $cells[7] ?? '';
                    $deliveryDate = $cells[8] ?? '';
                    $arrival = $cells[9] ?? '';
                    $status = $cells[10] ?? '';

                    // INN ajratish
                    $inn = '';
                    $recipientName = '';

                    if (preg_match('/^(\d+)\s+(.*)$/', $recipient, $m)) {
                        $inn = $m[1];
                        $recipientName = $m[2];
                    } else {
                        $recipientName = $recipient;
                    }

                    if (empty($inn) || strlen($inn) !== 9) {
                        $this->warn("⚠️ Invalid INN");
                        continue;
                    }

                    // Excel yozish
                    $sheet->setCellValue('A' . $rowIndex, $docNumber);
                    $sheet->setCellValue('B' . $rowIndex, $customCode);
                    $sheet->setCellValue('C' . $rowIndex, $customDate);
                    $sheet->setCellValue('D' . $rowIndex, $tebhn);
                    $sheet->setCellValue('E' . $rowIndex, $transport);
                    $sheet->setCellValue('F' . $rowIndex, $weight);
                    $sheet->setCellValue('G' . $rowIndex, $inn);
                    $sheet->setCellValue('H' . $rowIndex, $recipientName);
                    $sheet->setCellValue('I' . $rowIndex, $deliveryPost);
                    $sheet->setCellValue('J' . $rowIndex, $deliveryDate);
                    $sheet->setCellValue('K' . $rowIndex, $arrival);
                    $sheet->setCellValue('L' . $rowIndex, $status);

                    $rowIndex++;

                } catch (Exception $e) {
                    $this->warn("❌ Error: " . $e->getMessage());
                    continue;
                }
            }

            // Fayl saqlash
            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);

            $this->info("✅ DONE: {$filePath}");

        } catch (Exception $e) {
            $this->error('❌ ' . $e->getMessage());
        }
    }

    private function generateNextTransitId($startTransitId, $increment)
    {
        $prefix = substr($startTransitId, 0, 2);
        $year = substr($startTransitId, 2, 4);
        $number = (int)substr($startTransitId, 6);

        $newNumber = $number + $increment;

        return $prefix . $year . str_pad($newNumber, 7, '0', STR_PAD_LEFT);
    }
}