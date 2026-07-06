<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class ScrapeMintransData extends Command
{
    protected $signature = 'mintrans:scrape {start} {end} {path}';
    protected $description = 'Scrape Mintrans API and export to Excel';

    public function handle()
    {
        $start = (int)$this->argument('start');
        $end   = (int)$this->argument('end');
        $path  = $this->argument('path');

        $this->info("Scraping from $start to $end");

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'License Number',
            'State Number',
            'Company Name',
            'Company Address',
            'Company Phone',
            'Activity Type Name',
            'Transport Type',
            'Cargo Type',
            'Issue Date',
            'Expiry Date',
            'Status',
            'Regional Office',
            'Vehicle Model',
            'Seat Count / Load Capacity'
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col.'1', $header);
            $col++;
        }

        $rowIndex = 2;

        for ($i = $start; $i >= $end; $i--) {

            $this->info("Checking: $i");

            try {

                $response = Http::withHeaders([
                    'Content-Type' => 'application/json'
                ])->post(
                    'https://info.mintrans.uz/api0/check-data-by-search',
                    [
                        'number' => (string)$i
                    ]
                );

                if (!$response->ok()) {
                    continue;
                }

                $json = $response->json();

                if (!isset($json['data'])) {
                    continue;
                }

                $html = $json['data'];

                libxml_use_internal_errors(true);

                $dom = new \DOMDocument();
                $dom->loadHTML($html);

                $xpath = new \DOMXPath($dom);

                $vehicleModel = '';
                $seatCount = '';

                $h4 = $xpath->query("//h4")->item(0);

                if ($h4) {

                    $text = $h4->nodeValue;

                    preg_match('/Rusumi:\s*(.*?)\s*Yuk/', $text, $r);
                    preg_match('/Yuk ko\'tarish qobiliyati:\s*(.*)/', $text, $y);

                    $vehicleModel = trim($r[1] ?? '');
                    $seatCount = trim($y[1] ?? '');
                }

                $row = $xpath->query("//tbody/tr")->item(0);

                if (!$row) {
                    continue;
                }

                $cells = $row->getElementsByTagName('td');

                $license = trim($cells->item(1)->textContent);
                $license = preg_replace('/^\d{2}\.\d{2}\.\d{4}/', '', $license);

                $stateNumber = trim($cells->item(2)->textContent);

                $companyCell = $cells->item(3);
                $companyName = trim($companyCell->textContent);

                $companyAddress = '';
                $companyPhone = '';

                $tooltip = $companyCell->getElementsByTagName('em')->item(0);

                if ($tooltip) {

                    $tooltipText = $tooltip->textContent;

                    if (preg_match('/(.+),\s*(\d+)/', $tooltipText, $match)) {
                        $companyAddress = trim($match[1]);
                        $companyPhone = trim($match[2]);
                    }
                }

                $activity = trim($cells->item(4)->textContent);
                $transport = trim($cells->item(5)->textContent);
                $cargo = trim($cells->item(6)->textContent);
                $issue = trim($cells->item(7)->textContent);
                $expiry = trim($cells->item(8)->textContent);
                $status = trim($cells->item(9)->textContent);
                $region = trim($cells->item(10)->textContent);

                $sheet->setCellValue("A$rowIndex", $license);
                $sheet->setCellValue("B$rowIndex", $stateNumber);
                $sheet->setCellValue("C$rowIndex", $companyName);
                $sheet->setCellValue("D$rowIndex", $companyAddress);
                $sheet->setCellValueExplicit("E$rowIndex", $companyPhone, DataType::TYPE_STRING);
                $sheet->setCellValue("F$rowIndex", $activity);
                $sheet->setCellValue("G$rowIndex", $transport);
                $sheet->setCellValue("H$rowIndex", $cargo);
                $sheet->setCellValue("I$rowIndex", $issue);
                $sheet->setCellValue("J$rowIndex", $expiry);
                $sheet->setCellValue("K$rowIndex", $status);
                $sheet->setCellValue("L$rowIndex", $region);
                $sheet->setCellValue("M$rowIndex", $vehicleModel);
                $sheet->setCellValue("N$rowIndex", $seatCount);

                $rowIndex++;

            } catch (\Exception $e) {

                $this->error("Error: ".$e->getMessage());
            }

            usleep(150000);
        }

        $writer = new Xlsx($spreadsheet);

        $writer->save($path);

        $this->info("Finished!");
        $this->info("File saved: ".$path);
    }
}