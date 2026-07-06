<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\DomCrawler\Crawler;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class FillInnFromOrginfoCommand extends Command
{
    protected $signature = 'excel:fill-inn
                            {file : Excel file path}
                            {output=output.xlsx : Output file name}';

    protected $description = 'Fill INN column from orginfo.uz by company name';

    public function handle()
    {
        $filePath = $this->argument('file');
        $outputPath = $this->argument('output');

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $highestRow = $sheet->getHighestRow();
        for ($r = 2; $r <= $highestRow; $r++) {

            $phone = $sheet
                ->getCell("G{$r}")
                ->getFormattedValue();

            $sheet->setCellValueExplicit(
                "G{$r}",
                (string) $phone,
                DataType::TYPE_STRING
            );
        }

        // Header
        $sheet->setCellValue('A1', 'Chegara nomi');
        $sheet->setCellValue('B1', 'Davlatlar');
        $sheet->setCellValue('C1', 'Sana');
        $sheet->setCellValue('D1', 'INN');
        $sheet->setCellValue('E1', 'Firma nomi');
        $sheet->setCellValue('F1', 'Mashina raqami');

        for ($row = 2; $row <= $highestRow; $row++) {

            // E ustun = Firma nomi
            $originalCompanyName = trim(
                (string) $sheet->getCell("E{$row}")->getValue()
            );

            if (empty($originalCompanyName)) {
                continue;
            }

            // Kompaniya nomini tozalash
            $companyName = $this->cleanCompanyName($originalCompanyName);

            if (empty($companyName)) {
                continue;
            }

            $this->info("Searching: {$companyName}");

            try {

                $url = 'https://orginfo.uz/ru/search/all/?q=' . urlencode($companyName);

                $response = Http::timeout(30)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0'
                    ])
                    ->get($url);

                if (!$response->successful()) {

                    $this->error("REQUEST FAILED: {$companyName}");
                    continue;
                }

                $html = $response->body();

                $crawler = new Crawler($html);

                $inn = '';

                // Birinchi topilgan INN
                if (
                    $crawler->filter('.bg-success.bg-opacity-25.text-success')->count()
                ) {

                    $inn = trim(
                        $crawler
                            ->filter('.bg-success.bg-opacity-25.text-success')
                            ->first()
                            ->text('')
                    );
                }

                if (!empty($inn)) {

                    // D ustun = INN
                    $sheet->setCellValue("D{$row}", $inn);

                    $this->info("FOUND: {$companyName} => {$inn}");

                } else {

                    $this->warn("NOT FOUND: {$companyName}");
                }

                sleep(1);

            } catch (\Throwable $e) {

                $this->error("ERROR: {$companyName}");
                $this->error($e->getMessage());
            }
        }

 
        // Header style
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => [
                'bold' => true,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);


        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);

        $this->info("DONE => {$outputPath}");
    }

    private function cleanCompanyName(string $companyName): string
    {
        $companyName = trim($companyName);

        // Qo‘shtirnoqlarni olib tashlash
        $companyName = str_replace([
            '"',
            "'",
            '«',
            '»',
            '`',
        ], '', $companyName);

        // OOO / ООО / MCHJ va boshqalarni olib tashlash
        $patterns = [
            '/^OOO\s+/iu',
            '/^ООО\s+/iu',
            '/^MCHJ\s+/iu',
            '/^ЧП\s+/iu',
            '/^СП\s+/iu',
            '/^LLC\s+/iu',
        ];

        $companyName = preg_replace($patterns, '', $companyName);

        // Ortiqcha probellarni tozalash
        $companyName = preg_replace('/\s+/', ' ', $companyName);

        return trim($companyName);
    }
}