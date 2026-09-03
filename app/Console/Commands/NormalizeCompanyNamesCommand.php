<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class NormalizeCompanyNamesCommand extends Command
{
    protected $signature = 'excel:normalize-company
                            {file : Excel file}
                            {output=normalized.xlsx : Output file}';

    protected $description = 'Normalize company names to ООО "COMPANY" format';

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

        // Korxona nomi ustunini topish
        foreach (range('A', $highestColumn) as $column) {

            $header = trim((string)$sheet->getCell($column . '1')->getValue());

            if (mb_strtolower($header) === mb_strtolower('Korxona nomi')) {
                $companyColumn = $column;
                break;
            }
        }

        if (!$companyColumn) {
            $this->error("Korxona nomi ustuni topilmadi.");
            return Command::FAILURE;
        }

        $updated = 0;

        $this->output->progressStart($highestRow - 1);

        for ($row = 2; $row <= $highestRow; $row++) {

            $company = trim((string)$sheet->getCell($companyColumn.$row)->getValue());

            if ($company === '') {
                $this->output->progressAdvance();
                continue;
            }

            $normalized = $this->normalizeCompanyName($company);

            if ($normalized !== $company) {
                $sheet->setCellValue($companyColumn.$row, $normalized);
                $updated++;
            }

            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        $writer = new Xlsx($spreadsheet);
        $writer->save($output);

        $this->newLine();
        $this->info("Updated: {$updated}");
        $this->info("Saved: {$output}");

        return Command::SUCCESS;
    }

    private function normalizeCompanyName(string $name): string
    {
        $name = trim($name);

        // 1. Agar qo'shtirnoq bo'lsa faqat ichidagi nomni olamiz
        if (preg_match('/["«](.*?)["»]/u', $name, $matches)) {
            return 'ООО "' . trim($matches[1]) . '"';
        }

        // 2. Qo'shtirnoq bo'lmasa prefikslarni olib tashlaymiz
        $patterns = [

            '/^ООО\s+/iu',
            '/^OOO\s+/iu',
            '/^МЧЖ\s+/iu',
            '/^MCHJ\s+/iu',
            '/^ХК\s+/iu',
            '/^XK\s+/iu',
            '/^АЖ\s+/iu',
            '/^AJ\s+/iu',
            '/^ЧП\s+/iu',
            '/^СП\s+/iu',
            '/^LLC\s+/iu',

            '/ОРГАНИЗАЦИЯ\s+С\s+ОГРАНИЧЕННОЙ\s+ОТВЕТСТВЕННОСТЬЮ/iu',
            '/ОБЩЕСТВО\s+С\s+ОГРАНИЧЕННОЙ(\s+ИЛИ\s+ДОПОЛНИТЕЛЬНОЙ)?\s+ОТВЕТСТВЕННОСТЬЮ/iu',

            '/МАС.?УЛИЯТИ\s+ЧЕКЛАНГАН\s+ЖАМИЯТ/iu',
            '/MAS.?ULIYATI\s+CHEKLANGAN\s+JAMIYAT/iu',

            '/XUSUSIY\s+KORXONASI/iu',
            '/ХУСУСИЙ\s+КОРХОНАСИ/iu',
        ];

        $name = preg_replace($patterns, '', $name);

        $name = preg_replace('/\s+/', ' ', $name);

        $name = trim($name, " \t\n\r\0\x0B\"'«»");

        return 'ООО "' . $name . '"';
    }
}