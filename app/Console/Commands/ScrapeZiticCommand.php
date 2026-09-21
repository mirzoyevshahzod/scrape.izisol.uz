<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use App\Models\Auto;

class ScrapeZiticCommand extends Command
{
    protected $signature = 'scrape:zitic-command';

    protected $description =
        'Scrape zitic.ru via HTML, save Excel and send records to API';

    private string $apiUrl =
        'https://358b-93-188-81-10.ngrok-free.app/api/checkpoints/queue-records';

    public function handle()
    {
        $this->info("🚀 Zitic saytiga ulanmoqda...");

        try {

            /*
             * ==============================
             * GET HTML
             * ==============================
             */
            $response = Http::withoutVerifying()
                ->timeout(60)
                ->retry(3, 1000)
                ->get(
                    'https://zitic.ru/eo/vl/'
                );

            if (! $response->successful()) {

                $this->error(
                    "❌ Sayt ochilmadi. HTTP: " .
                    $response->status()
                );

                return Command::FAILURE;
            }

            $html = $response->body();

            /*
             * ==============================
             * PARSE HTML
             * ==============================
             */
            $crawler = new \Symfony\Component\DomCrawler\Crawler(
                $html
            );

            $rows = $crawler->filter('tbody tr');

            $this->info(
                "Rowlar soni: " . $rows->count()
            );

            /*
             * ==============================
             * EXCEL
             * ==============================
             */
            $spreadsheet = new Spreadsheet();

            $sheet = $spreadsheet->getActiveSheet();

            $sheet->fromArray(
                [
                    'Plate',
                    'Queue',
                    'Date',
                    'Status',
                    'Note',
                    'Company',
                    'Phone',
                    'States',
                    'Border',
                ],
                null,
                'A1'
            );

            $rowIndex = 2;

            /*
             * ==============================
             * API RECORDS
             * ==============================
             */
            $records = [];

            /*
             * Zitic status -> Backend status
             */
            $statusMap = [
                'В пути'       => 'passed',
                'Приглашён'    => 'passed',
                'Ожидание'     => 'in_queue',
                'Подтверждена' => 'in_queue',
            ];

            $rows->each(function ($row) use (
                &$rowIndex,
                &$records,
                $sheet,
                $statusMap
            ) {

                try {

                    /*
                     * ==============================
                     * PLATE
                     * ==============================
                     */
                    $plate = strtoupper(
                        trim(
                            $row
                                ->filter('.plate-number')
                                ->text('')
                        )
                    );

                    $plate = preg_replace(
                        '/\s+/',
                        '',
                        $plate
                    );

                    if (! $plate) {
                        return;
                    }
                    

                    /*
                     * ==============================
                     * OTHER DATA
                     * ==============================
                     */
                    $queue = trim(
                        $row
                            ->filter('.queue-number')
                            ->text('')
                    );

                    $date = trim(
                        $row
                            ->filter('.registration-date')
                            ->text('')
                    );

                    $statusText = trim(
                        $row
                            ->filter('.status-badge')
                            ->text('')
                    );

                    $tds = $row->filter('td');

                    $note = '';

                    if ($tds->count() >= 5) {
                        $note = trim(
                            $tds->eq(4)->text()
                        );
                    }

                    /*
                     * ==============================
                     * STATUS MAPPING
                     * ==============================
                     */
                    $status = $statusMap[
                        $statusText
                    ] ?? null;

                    if ($status === null) {

                        $this->warn(
                            "Noma'lum status: {$statusText} | {$plate}"
                        );

                        return;
                    }

                    /*
                     * ==============================
                     * AUTO
                     * ==============================
                     */
                    $auto = Auto::where(
                        'state_number',
                        $plate
                    )->first();

                    $company =
                        $auto?->company_name ?? '';

                    $phone =
                        $auto?->phone ?? '';

                    /*
                     * ==============================
                     * DATE
                     * ==============================
                     */
                    if (! $date) {

                        $this->warn(
                            "Sana topilmadi: {$plate}"
                        );

                        return;
                    }

                    try {

                        $dateTime =
                            \Carbon\Carbon::parse(
                                $date
                            );

                    } catch (\Throwable $e) {

                        $this->warn(
                            "Sana parse bo'lmadi: {$date} | {$plate}"
                        );

                        return;
                    }

                    /*
                     * ==============================
                     * API RECORD
                     * ==============================
                     */
                    $records[] = [

                        'checkpoint_external_id' =>
                            '',

                        'checkpoint' =>
                            'Владикавказ — Казбеги',

                        'plate_number' =>
                            $plate,

                        'record_date' =>
                            $dateTime->format(
                                'Y-m-d'
                            ),

                        'time_slot_start' =>
                            $dateTime->format(
                                'H:i'
                            ),

                        'time_slot_end' =>
                            null,

                        'status' =>
                            $status,

                        'scraped_at' =>
                            now()->format(
                                'Y-m-d H:i:s'
                            ),
                    ];

                    /*
                     * ==============================
                     * EXCEL
                     * ==============================
                     */
                    $sheet->setCellValue(
                        "A{$rowIndex}",
                        $plate
                    );

                    $sheet->setCellValue(
                        "B{$rowIndex}",
                        $queue
                    );

                    $sheet->setCellValue(
                        "C{$rowIndex}",
                        $date
                    );

                    $sheet->setCellValue(
                        "D{$rowIndex}",
                        $statusText
                    );

                    $sheet->setCellValue(
                        "E{$rowIndex}",
                        $note
                    );

                    $sheet->setCellValue(
                        "F{$rowIndex}",
                        $company
                    );

                    $sheet->setCellValueExplicit(
                        "G{$rowIndex}",
                        (string) $phone,
                        DataType::TYPE_STRING
                    );

                    $sheet->setCellValue(
                        "H{$rowIndex}",
                        'Россия Грузия'
                    );

                    $sheet->setCellValue(
                        "I{$rowIndex}",
                        'Зитик'
                    );

                    $rowIndex++;

                    $this->info(
                        "Topildi: {$plate} | {$statusText} -> {$status}"
                    );

                } catch (\Throwable $e) {

                    \Log::error(
                        "Row parsing error: " .
                        $e->getMessage()
                    );

                    $this->warn(
                        "Row xatosi: " .
                        $e->getMessage()
                    );
                }
            });

            /*
             * ==============================
             * SEND TO API
             * ==============================
             */
            if (! empty($records)) {

                $this->info(
                    "API ga yuborilmoqda: " .
                    count($records) .
                    " ta record"
                );

                $written =
                    $this->sendRecordsToApi(
                        $records
                    );

                $this->info(
                    "API written: {$written}"
                );

            } else {

                $this->warn(
                    "API ga yuboriladigan record topilmadi."
                );
            }

            /*
             * ==============================
             * SAVE EXCEL
             * ==============================
             */
            $date = now()->format(
                'Y-m-d_H-i-s'
            );

            $fileName =
                "zitic_{$date}.xlsx";

            $directory =
                storage_path('app/zitic');

            if (! is_dir($directory)) {
                mkdir(
                    $directory,
                    0755,
                    true
                );
            }

            $filePath =
                "{$directory}/{$fileName}";

            $writer =
                new Xlsx($spreadsheet);

            $writer->save(
                $filePath
            );

            $this->info(
                "Excel saved: {$filePath}"
            );

        } catch (\Throwable $e) {

            \Log::error(
                "❌ Xatolik: " .
                $e->getMessage()
            );

            $this->error(
                "❌ Xatolik: " .
                $e->getMessage()
            );

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /*
     * ==============================
     * SEND RECORDS TO API
     * ==============================
     */
    private function sendRecordsToApi(
        array $records
    ): int {

        try {

            $response = Http::timeout(60)
                ->retry(3, 1000)
                ->acceptJson()
                ->withHeaders([
                    'X-API-KEY' =>
                        config(
                            'services.checkpoint_scraper.api_key'
                        ),
                ])
                ->post(
                    $this->apiUrl,
                    [
                        'records' => $records,
                    ]
                );

        } catch (\Throwable $e) {

            $this->error(
                'API request xatosi: ' .
                $e->getMessage()
            );

            return 0;
        }

        if (! $response->successful()) {

            $this->error(
                'API HTTP error: ' .
                $response->status()
            );

            $this->error(
                'Response: ' .
                $response->body()
            );

            return 0;
        }

        $data =
            $response->json();

        $this->info(
            'API response: ' .
            $response->body()
        );

        return (int) (
            $data['written'] ?? 0
        );
    }
}