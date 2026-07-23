<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Symfony\Component\DomCrawler\Crawler;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Storage;

class ScrapeZanjeerOperators extends Command
{
    protected $signature = 'scrape:zanjeer-operators {file}';

    protected $description = 'Get operator names from Zanjeer and write into excel';

    public function handle()
    {
        $relativePath = $this->argument('file');

        // =========================
        // FILE CHECK
        // =========================
        $inputFile = storage_path('app/' . $relativePath);

        if (!file_exists($inputFile)) {

            \Log::error("INPUT FILE NOT FOUND: {$inputFile}");

            $this->error('FILE NOT FOUND');

            return Command::FAILURE;
        }

        // =========================
        // COOKIE
        // =========================
        $cookieJar = CookieJar::fromArray([

            'XSRF-TOKEN' => 'eyJpdiI6IkRCcWxoWWt6Ujc4VXZMazlNaXpYNVE9PSIsInZhbHVlIjoiUG13ejR4b2dtUSs4VjRYbUxrQ2ErK3E5YUZoNFZMUGdTbUY2dTBXek9WZ080VXNaVGRnR2QvVmdpcGxJOTErZE5JaUpCZHRqVkNPc29GVGpPSm81TDFySTZJTkQvYzdnTk9NYVJMM3NvUUdzcUZMZ0Y1TzNidTZUVlIvN1QwSkwiLCJtYWMiOiJiYmYxZWJjNmJiNWMzZjdhZDJmYjgzYzg1OTE5MDg1ZTI2NjA2YWRiNjc2MjQyNmRmODI2YWUyODBmY2M0MGVkIiwidGFnIjoiIn0%3D',

            'zanjeer_crm_session' => 'eyJpdiI6Ijlxa2krZmxKamRIYjhyaGJ2TUVwY1E9PSIsInZhbHVlIjoiVTdBa0tvL0ZNSkhzanNFNFVnTGxjTzhiaGZlZy8ydGdhSzhTbWdNWU1ub2lETStMMUc0YVMxVUhvb3pmZEMvM3Z0WFRuV0xFNFNiOE9SN2lad3I4TkRQM28xdW95SlVzV0UzWjBQR3IxcjRQejFkamg4YVh6YVJaZzhXOTM1cDYiLCJtYWMiOiJkY2MxNDljMjJlYTBjMDBmNGRiMmZjMmVlOTdhODlkOTQ1YzgyMjQzYzlkODliNjBhMzNjMTc2M2NlMWMwYTgwIiwidGFnIjoiIn0%3D'

        ], 'crm.zanjeer.uz');

        // =========================
        // CLIENT
        // =========================
        $client = new Client([

            'verify' => false,

            'cookies' => $cookieJar,

            'headers' => [

                'Accept' => 'application/json',

                'User-Agent' => 'Mozilla/5.0',
            ]
        ]);

        try {

            // =========================
            // OPEN PAGE
            // =========================
            $pageResponse = $client->get(
                'https://crm.zanjeer.uz/users/contragents'
            );

            $html = $pageResponse
                ->getBody()
                ->getContents();

            // =========================
            // CSRF TOKEN
            // =========================
            preg_match(
                '/<meta name="csrf-token" content="([^"]+)"/',
                $html,
                $csrfMatch
            );

            $csrfToken = $csrfMatch[1] ?? null;

            if (!$csrfToken) {

                $this->error('CSRF TOKEN NOT FOUND');

                return Command::FAILURE;
            }

            // =========================
            // SNAPSHOT
            // =========================
            preg_match_all(
                '/wire:snapshot="([^"]+)"/',
                $html,
                $snapshotMatches
            );

            $tableSnapshot = null;

            foreach ($snapshotMatches[1] as $encodedSnapshot) {

                $decoded = html_entity_decode($encodedSnapshot);

                $snapshotData = json_decode($decoded, true);

                if (
                    isset($snapshotData['memo']['name']) &&
                    $snapshotData['memo']['name'] === 'data-table'
                ) {

                    $tableSnapshot = $decoded;

                    break;
                }
            }

            if (!$tableSnapshot) {

                $this->error('TABLE SNAPSHOT NOT FOUND');

                return Command::FAILURE;
            }

            // =========================
            // LOAD INPUT EXCEL
            // =========================
            $spreadsheet = IOFactory::load($inputFile);
            

            $sheet = $spreadsheet->getActiveSheet();

            $highestRow = $sheet->getHighestRow();

            // =========================
            // HEADER
            // =========================
            $sheet->setCellValue('H1', 'Meneger Ismi sharifi');

            // =========================
            // LOOP
            // =========================
            for ($row = 2; $row <= $highestRow; $row++) {

                // =========================
                // GET COMPANY NAME
                // =========================
                $company = trim(
                    $sheet
                        ->getCell("E$row")
                        ->getValue()
                );

                if (!$company) {
                    continue;
                }

                // =========================
                // NORMALIZE COMPANY
                // =========================
                $searchCompany = $company;

                // OOO / ООО remove
                $searchCompany = preg_replace(
                    '/\b(OOO|ООО)\b/ui',
                    '',
                    $searchCompany
                );

                // " remove
                $searchCompany = str_replace(
                    '"',
                    '',
                    $searchCompany
                );

                // extra spaces remove
                $searchCompany = preg_replace(
                    '/\s+/',
                    ' ',
                    $searchCompany
                );

                $searchCompany = trim($searchCompany);

                $this->line("SEARCH => " . $searchCompany);

                try {

                    // =========================
                    // PAYLOAD
                    // =========================
                    $payload = [

                        '_token' => $csrfToken,

                        'components' => [
                            [

                                'snapshot' => $tableSnapshot,

                                'updates' => [
                                    'search' => $searchCompany
                                ],

                                'calls' => []
                            ]
                        ]
                    ];

                    // =========================
                    // REQUEST
                    // =========================
                    $response = $client->post(
                        'https://crm.zanjeer.uz/livewire/update',
                        [

                            'headers' => [

                                'X-Livewire' => 'true',

                                'X-CSRF-TOKEN' => $csrfToken,

                                'X-Requested-With' => 'XMLHttpRequest',

                                'Origin' => 'https://crm.zanjeer.uz',

                                'Referer' => 'https://crm.zanjeer.uz/users/contragents',

                                'Accept' => 'application/json',
                            ],

                            'json' => $payload
                        ]
                    );

                    $responseData = json_decode(
                        $response->getBody()->getContents(),
                        true
                    );

                    $responseHtml =
                        $responseData['components'][0]['effects']['html']
                        ?? '';

                    $operator = '-';

                    // =========================
                    // PARSE RESPONSE
                    // =========================
                    if ($responseHtml) {

                        $crawler = new Crawler($responseHtml);

                        $rows = $crawler->filter('tbody tr');

                        if ($rows->count() > 0) {

                            $firstRow = $rows->first();

                            $tds = $firstRow->filter('td');

                            foreach ($tds as $td) {
                                

                                $value = trim(
                                    html_entity_decode(
                                        strip_tags($td->textContent),
                                        ENT_QUOTES | ENT_HTML5,
                                        'UTF-8'
                                    )
                                );

                                if (
                                    preg_match(
                                        '/^[A-ZА-ЯЎҚҒҲ][A-ZА-ЯЎҚҒҲa-zа-яўқғҳ\'\-\s]+$/u',
                                        $value
                                    )
                                    &&
                                    mb_strlen($value) > 10
                                    &&
                                    !str_contains($value, 'MCHJ')
                                    &&
                                    !str_contains($value, 'ООО')
                                    &&
                                    !str_contains($value, 'ФИО')
                                ) {
                                    if($value === 'EGS OPERATION'){
                                        $value = '-';
                                    }
                                    $operator = $value;
                                }
                            }
                        }
                    }

                    // =========================
                    // WRITE OPERATOR
                    // =========================
                    $sheet->setCellValue(
                        "H$row",
                        $operator
                    );

                    $this->info(
                        "ROW {$row} => {$operator}"
                    );

                    sleep(1);

                } catch (\Exception $e) {

                    $sheet->setCellValue(
                        "H$row",
                        'ERROR'
                    );

                    $this->error(
                        "ROW {$row} ERROR => " .
                        $e->getMessage()
                    );
                }
            }

            // =========================
            // SAVE FILE
            // =========================
            $date = now()->format('Y-m-d_H-i-s');

            $outputFile = Storage::disk('public')->path(
                "operators_{$date}.xlsx"
            );

            $writer = new Xlsx($spreadsheet);

            $writer->save($outputFile);

            // =========================
            // LAST FILE
            // =========================
            file_put_contents(
                storage_path('app/last_operator_file.txt'),
                $outputFile
            );

            $this->info("DONE => {$outputFile}");

        } catch (\Exception $e) {

            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
