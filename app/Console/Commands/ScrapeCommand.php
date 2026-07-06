<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use App\Models\TurkeyData;
use App\Models\Auto;

class ScrapeCommand extends Command
{
    protected $signature = 'scrape:html';
    protected $description = 'Scrape hopatirparki.com via HTML (NO Selenium, NO duplicates)';

    private array $uzbekPatterns = [
        '/^(01|10|20|25|30|40|50|60|70|75|80|85|90|95)[A-Z]\d{3}[A-Z]{2}$/',
        '/^(01|10|20|25|30|40|50|60|70|75|80|85|90|95)\d{3}[A-Z]{3}$/',
        '/^(01|10|20|25|30|40|50|60|70|75|80|85|90|95)\d{4}[A-Z]{2}$/',
    ];

    public function handle()
    {
        $this->info('HTML scraping started (single page)...');

        // 1️⃣ Sahifani BIR MARTA yuklaymiz
        $html = Http::timeout(30)
            ->get('https://www.hopatirparki.com/tirparki/arhavilimansiragumruklu.asp')
            ->body();

        $crawler = new Crawler($html);
        $rows = $crawler->filter('#myTable tbody tr');

        if ($rows->count() === 0) {
            $this->error('No rows found!');
            return;
        }

        // 2️⃣ Excel init
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['Tartib raqami', 'Kirish tartib raqami', 'Avtomobil raqami', 'Sana', 'Kirish joyi', 'Company Name', 'Phone', 'States'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(chr(65 + $i) . '1', $h);
        }

        $rowIndex = 2;
        $usedPlates = [];

        // 3️⃣ Jadvalni o‘qiymiz
        foreach ($rows as $tr) {
            $td = (new Crawler($tr))->filter('td');

            if ($td->count() < 5) {
                continue;
            }

            $plakaRaw = trim($td->eq(2)->text());
            $plaka = strtoupper(
                preg_replace('/\s*\(.*\)/', '', explode("\n", $plakaRaw)[0])
            );

            // ❗ faqat O‘zbekiston raqamlari
            if (!$this->isUzbekVehicle($plaka)) {
                continue;
            }

            // ❗ duplicate himoyasi
            if (isset($usedPlates[$plaka])) {
                continue;
            }
            $usedPlates[$plaka] = true;

            
            $data = [
                'sira'  => trim($td->eq(0)->text()),
                'giris' => trim($td->eq(1)->text()),
                'plaka' => $plaka,
                'tarih' => trim($td->eq(3)->text()),
                'yer'   => trim($td->eq(4)->text()),
            ];

            $auto = Auto::where('state_number', $plaka)
                ->first();

            $companyName = '';
            $phone = '';

            if ($auto) {

                $companyName =
                    $auto->company_name ?? '';

                $phone =
                    $auto->phone ?? '';
            }

             TurkeyData::updateOrCreate(
                ['plaka' => $plaka],
                $data
            );

            $sheet->setCellValue("A{$rowIndex}", trim($td->eq(0)->text()));
            $sheet->setCellValue("B{$rowIndex}", trim($td->eq(1)->text()));
            $sheet->setCellValue("C{$rowIndex}", $plaka);
            $sheet->setCellValue("D{$rowIndex}", trim($td->eq(3)->text()));
            $sheet->setCellValue("E{$rowIndex}", trim($td->eq(4)->text()));
            $sheet->setCellValue("F{$rowIndex}", $companyName);
            $sheet->setCellValueExplicit(
                "G{$rowIndex}",
                (string) $phone,
                DataType::TYPE_STRING
            );
            $sheet->setCellValue("H{$rowIndex}", 'Turkiya Gruziya');

            $rowIndex++;
        }

        // 4️⃣ Excel saqlash
        $file = storage_path('app/turkey/turkey_scrape-' . now()->format('Y-m-d-H-i-s') . '.xlsx');
        (new Xlsx($spreadsheet))->save($file);

        $this->info("Excel saved: {$file}");
        $this->info('DONE ✅ (takrorlarsiz)');
    }

    private function isUzbekVehicle(string $plate): bool
    {
        $plate = preg_replace('/[^A-Z0-9]/', '', $plate);

        foreach ($this->uzbekPatterns as $pattern) {
            if (preg_match($pattern, $plate)) {
                return true;
            }
        }
        return false;
    }
}
