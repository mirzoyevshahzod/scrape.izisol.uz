<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Auto;
use PhpOffice\PhpSpreadsheet\IOFactory;

class UpdateCompanyNamesCommand extends Command
{
    protected $signature = 'excel:update-company-names
                            {file : Excel file}
                            {output=output.xlsx : Output file}';

    protected $description = 'Replace company names using INN from autos table';

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

        // Headerlarni o'qish
        $highestColumn = $sheet->getHighestColumn();
        $headers = [];

        foreach (range('A', $highestColumn) as $column) {
            $headers[$column] = trim((string)$sheet->getCell($column.'1')->getValue());
        }

        $companyColumn = null;
        $innColumn = null;

        foreach ($headers as $column => $header) {

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

        $highestRow = $sheet->getHighestRow();

        $updated = 0;

        for ($row = 2; $row <= $highestRow; $row++) {

            $tin = trim((string)$sheet->getCell($innColumn.$row)->getValue());

            if ($tin == '') {
                continue;
            }

            $auto = Auto::where('tin', $tin)->first();

            if ($auto && $auto->company_name) {

                $sheet->setCellValue(
                    $companyColumn.$row,
                    $auto->company_name
                );

                $updated++;
            }
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($output);

        $this->info("Done.");
        $this->info("Updated rows: {$updated}");
        $this->info("Saved: {$output}");

        return Command::SUCCESS;
    }
}