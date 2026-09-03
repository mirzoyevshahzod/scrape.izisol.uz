<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\DomCrawler\Crawler;

class FillInnFromCompanyCommand extends Command
{
    protected $signature = 'excel:fill-inn1
                            {file : Excel file}
                            {output=output.xlsx : Output file}';

    protected $description = 'Fill empty INN values from orginfo.uz using company name';

    public function handle()
    {
        $file = $this->argument('file');
        $output = $this->argument('output');

        if (!file_exists($file)) {
            $this->error("File not found.");
            return Command::FAILURE;
        }

        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();

        $highestColumn = $sheet->getHighestColumn();
        $highestRow = $sheet->getHighestRow();

        $companyColumn = null;
        $innColumn = null;

        // Headerlarni topish
        foreach (range('A', $highestColumn) as $column) {

            $header = trim((string)$sheet->getCell($column . '1')->getValue());

            if (mb_strtolower($header) == mb_strtolower('Korxona nomi')) {
                $companyColumn = $column;
            }

            if (mb_strtolower($header) == 'inn') {
                $innColumn = $column;
            }
        }

        if (!$companyColumn || !$innColumn) {
            $this->error("Korxona nomi yoki INN ustuni topilmadi.");
            return Command::FAILURE;
        }

        $updated = 0;
        $notFound = 0;
        $skipped = 0;

        $this->info("Total rows: " . ($highestRow - 1));
        $this->output->progressStart($highestRow - 1);

        for ($row = 2; $row <= $highestRow; $row++) {

        $currentInn = trim((string)$sheet->getCell($innColumn.$row)->getFormattedValue());

        if ($currentInn !== '') {
            $this->info("[$row] SKIPPED (INN mavjud: {$currentInn})");
            $skipped++;
            $this->info("[$row] SKIPPED (INN mavjud: {$currentInn})");
            $this->output->progressAdvance();
            continue;
        }


            $inn = trim((string)$sheet->getCell($innColumn . $row)->getValue());

            if (!empty($inn)) {
                $skipped++;
                $this->output->progressAdvance();
                continue;
            }

            $originalCompany = trim((string)$sheet->getCell($companyColumn . $row)->getValue());

            if (empty($originalCompany)) {
                $this->output->progressAdvance();
                continue;
            }

            $company = $this->cleanCompanyName($originalCompany);

            if (empty($company)) {
                $this->output->progressAdvance();
                continue;
            }

            $this->newLine();
            $this->info("[$row] Searching: {$company}");

            try {

                $url = 'https://orginfo.uz/ru/search/all/?q=' . urlencode($company);

                $response = Http::timeout(30)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0'
                    ])
                    ->get($url);

                if (!$response->successful()) {
                    $this->warn("Request failed.");
                    $this->output->progressAdvance();
                    continue;
                }

                $crawler = new Crawler($response->body());

                $foundInn = '';

                if ($crawler->filter('.bg-success.bg-opacity-25.text-success')->count()) {

                    $foundInn = trim(
                        $crawler
                            ->filter('.bg-success.bg-opacity-25.text-success')
                            ->first()
                            ->text('')
                    );
                }

                if (!empty($foundInn)) {

                    $sheet->setCellValue($innColumn . $row, $foundInn);

                    $updated++;

                    $this->info("FOUND: {$foundInn}");

                } else {

                    $notFound++;

                    $this->warn("NOT FOUND");
                }

                sleep(1);

            } catch (\Throwable $e) {

                $this->error($e->getMessage());
            }

            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        $writer = new Xlsx($spreadsheet);
        $writer->save($output);

        $this->newLine();
        $this->info("======================================");
        $this->info("Updated : {$updated}");
        $this->info("Skipped : {$skipped}");
        $this->info("Not Found : {$notFound}");
        $this->info("Saved : {$output}");
        $this->info("======================================");

        return Command::SUCCESS;
    }

    private function cleanCompanyName(string $companyName): string
    {
        $companyName = trim($companyName);

        // Avval qo'shtirnoq ichidagi nomni olish
        if (preg_match('/["«](.*?)["»]/u', $companyName, $matches)) {
            return trim($matches[1]);
        }

        $companyName = str_replace([
            '"',
            "'",
            '«',
            '»',
            '`',
        ], '', $companyName);

        $patterns = [
            '/^OOO\s+/iu',
            '/^ООО\s+/iu',
            '/^MCHJ\s+/iu',
            '/^ЧП\s+/iu',
            '/^СП\s+/iu',
            '/^LLC\s+/iu',
            '/^ХК\s+/iu',
            '/^XK\s+/iu',
            '/XUSUSIY\s+KORXONASI/iu',
            '/MAS\'?ULIYATI\s+CHEKLANGAN\s+JAMIYATI/iu',
            '/ОБЩЕСТВО\s+С\s+ОГРАНИЧЕННОЙ\s+ОТВЕТСТВЕННОСТЬЮ/iu',
        ];

        $companyName = preg_replace($patterns, '', $companyName);

        $companyName = preg_replace('/\s+/', ' ', $companyName);

        return trim($companyName);
    }
}