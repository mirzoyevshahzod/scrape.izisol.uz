<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\DomCrawler\Crawler;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use App\Models\Auto;

class ScrapeZiticHtml extends Command
{
    protected $signature = 'scrape:zitic-html';

    protected $description = 'Scrape zitic.ru via HTML and save Excel';

    public function handle()
    {
        $this->info("🚀 Saytga ulanmoqda...");

        try {

            // ===== GET HTML =====
            $response = Http::withoutVerifying()
            ->timeout(60)
            ->get('https://zitic.ru/eo/vl/');

            if (!$response->successful()) {
                $this->error("❌ Sayt ochilmadi");
                return Command::FAILURE;
            }

            $html = $response->body();

            // ===== PARSE HTML =====
            $crawler = new Crawler($html);

            $rows = $crawler->filter('tbody tr');

           \Log::info("Rowlar soni: " . $rows->count());

            // ===== EXCEL =====
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $sheet->fromArray([
                'Plate',
                'Queue',
                'Date',
                'Status',
                'Note',
                'Company',
                'Phone',
                'States',
                'Border'
            ], null, 'A1');

            $rowIndex = 2;

            $validRegions = [
                '01', '10', '20', '25', '30',
                '40', '50', '60', '70', '80',
                '85', '90', '95'
            ];

            $rows->each(function ($row) use (
                &$rowIndex,
                $sheet,
                $validRegions
            ) {

                try {

                    $crawler = $row;

                    $plate = strtoupper(
                        trim(
                            $crawler->filter('.plate-number')->text('')
                        )
                    );

                    $plate = preg_replace('/\s+/', '', $plate);

                    $queue = trim(
                        $crawler->filter('.queue-number')->text('')
                    );

                    $date = trim(
                        $crawler->filter('.registration-date')->text('')
                    );

                    $status = trim(
                        $crawler->filter('.status-badge')->text('')
                    );

                    $tds = $crawler->filter('td');

                    $note = '';

                    if ($tds->count() >= 5) {
                        $note = trim($tds->eq(4)->text());
                    }

                    // ===== EMPTY SKIP =====
                    if (!$plate) {
                        return;
                    }

                    // ===== ONLY UZBEK PLATES =====

                    $isValidPlate =
                        preg_match('/^\d{2}\d{3}[A-Z]{3}$/', $plate) ||
                        preg_match('/^\d{2}[A-Z]\d{3}[A-Z]{2}$/', $plate);

                    if (!$isValidPlate) {
                        return;
                    }

                    $regionCode = substr($plate, 0, 2);

                    if (!in_array($regionCode, $validRegions)) {
                        return;
                    }

                    // ===== DB CHECK =====
                    $auto = Auto::where('state_number', $plate)->first();

                    $company = $auto?->company_name ?? '';
                    $phone = $auto?->phone ?? '';

                    // ===== WRITE EXCEL =====
                    $sheet->setCellValue("A$rowIndex", $plate);
                    $sheet->setCellValue("B$rowIndex", $queue);
                    $sheet->setCellValue("C$rowIndex", $date);
                    $sheet->setCellValue("D$rowIndex", $status);
                    $sheet->setCellValue("E$rowIndex", $note);
                    $sheet->setCellValue("F$rowIndex", $company);
                    $sheet->setCellValueExplicit("G$rowIndex", (string) $phone, DataType::TYPE_STRING);
                    $sheet->setCellValue("H$rowIndex", 'Россия Грузия');
                    $sheet->setCellValue("I$rowIndex", 'Зитик');
                    $rowIndex++;

                } catch (\Exception $e) {

                    \Log::error("Row parsing error: " . $e->getMessage());
                }
            });

            // ===== SAVE =====
            $date = now()->format('Y-m-d_H-i-s');

            $fileName = "zitic_$date.xlsx";

            $filePath = storage_path("app/zitic/$fileName");

            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);   

            $this->line("FILE_PATH=" . $filePath);


        } catch (\Exception $e) {

            \Log::error("❌ Xatolik: " . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}