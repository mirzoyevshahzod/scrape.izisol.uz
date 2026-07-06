<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class MintransCommand extends Command
{
    protected $signature = 'mintrans:parse {file} {jobId}';
    protected $description = 'Parse Mintrans data';

    public function handle()
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("Fayl topilmadi");
            return;
        }

        $reader = IOFactory::createReaderForFile($filePath);
        $spreadsheet = $reader->load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $highestRow = $sheet->getHighestRow();

        $this->info("Topilgan qatorlar: $highestRow");

        $result = new Spreadsheet();
        $newSheet = $result->getActiveSheet();

        $headers = [
            'Rusumi',
            "Yuk ko'tarish qobiliyati",
            'Litsenziya varaqasi',
            'Davlat raqami',
            'Korxona nomi',
            'Faoliyat turi',
            'Transport turi',
            'Yuk turi',
            'Berilgan sana',
            'Amal qilish muddati',
            'Holati',
            'Hududiy boshqarma'
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $newSheet->setCellValue($col . '1', $header);
            $col++;
        }

        $rowIndex = 2;

        for ($i = 2; $i <= $highestRow; $i++) {

            $car = trim($sheet->getCell("A$i")->getValue());

            if (!$car) {
                continue;
            }

            $this->info("Checking: $car");

            try {

                $payload = json_encode([
                    "autoN" => $car
                ]);

                $cmd = "curl -s -X POST https://info.mintrans.uz/api0/check-data-by-search -H 'Content-Type: application/json' -d '$payload'";

                $response = shell_exec($cmd);

                if (!$response) {
                    continue;
                }

                $json = json_decode($response, true);

                if (!isset($json['data'])) {
                    continue;
                }

                $html = $json['data'];

                libxml_use_internal_errors(true);

                $dom = new \DOMDocument();
                $dom->loadHTML($html);

                $xpath = new \DOMXPath($dom);

                // Rusumi va Yuk qobiliyati
                $rusumi = '';
                $yuk = '';

                $h4 = $xpath->query("//h4")->item(0);

                if ($h4) {

                    $text = $h4->nodeValue;

                    preg_match('/Rusumi:\s*(.*?)\s*Yuk/', $text, $r);
                    preg_match('/Yuk ko\'tarish qobiliyati:\s*(.*)/', $text, $y);

                    $rusumi = trim($r[1] ?? '');
                    $yuk = trim($y[1] ?? '');
                }

                $row = $xpath->query("//tbody/tr")->item(0);

                if (!$row) {
                    continue;
                }

                $cells = $row->getElementsByTagName('td');

                $license = trim($cells->item(1)->textContent);
                $license = preg_replace('/^\d{2}\.\d{2}\.\d{4}/', '', $license);
                $license = trim($license);

                $stateNumber = trim($cells->item(2)->textContent);
                $company = trim($cells->item(3)->textContent);
                $faoliyat = trim($cells->item(4)->textContent);
                $transport = trim($cells->item(5)->textContent);
                $yukTuri = trim($cells->item(6)->textContent);
                $berilgan = trim($cells->item(7)->textContent);
                $amal = trim($cells->item(8)->textContent);
                $holat = trim($cells->item(9)->textContent);
                $hudud = trim($cells->item(10)->textContent);

                $newSheet->setCellValue("A$rowIndex", $rusumi);
                $newSheet->setCellValue("B$rowIndex", $yuk);
                $newSheet->setCellValue("C$rowIndex", $license);
                $newSheet->setCellValue("D$rowIndex", $stateNumber);
                $newSheet->setCellValue("E$rowIndex", $company);
                $newSheet->setCellValue("F$rowIndex", $faoliyat);
                $newSheet->setCellValue("G$rowIndex", $transport);
                $newSheet->setCellValue("H$rowIndex", $yukTuri);
                $newSheet->setCellValue("I$rowIndex", $berilgan);
                $newSheet->setCellValue("J$rowIndex", $amal);
                $newSheet->setCellValue("K$rowIndex", $holat);
                $newSheet->setCellValue("L$rowIndex", $hudud);

                $rowIndex++;

            } catch (\Exception $e) {

                $this->error("Xato: " . $e->getMessage());
            }

            usleep(200000);
        }

        $jobId = $this->argument('jobId');

        $path = storage_path("app/public/results/result_$jobId.xlsx");

        $writer = new Xlsx($result);
        $writer->save($path);

        $this->info("Tayyor: $path");
    }
}