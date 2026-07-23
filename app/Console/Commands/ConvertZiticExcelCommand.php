<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ConvertZiticExcelCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zitic:convert {input} {output}';

    protected $description = 'Queue excel faylini yangi formatga o‘tkazadi';

    /**
     * Execute the console command.
     */
     public function handle()
    {
        $input = $this->argument('input');

        $output = $this->argument('output');

        if (!$output) {
            $output = storage_path('app/converted.xlsx');
        }

        $spreadsheet = IOFactory::load($input);

        $sheet = $spreadsheet->getActiveSheet();

        $newSpreadsheet = new Spreadsheet();

        $newSheet = $newSpreadsheet->getActiveSheet();

        // Header
        $newSheet->setCellValue('A1', 'Avtomobil raqami');
        $newSheet->setCellValue('B1', 'Sana');

        $newRow = 2;

        foreach ($sheet->getRowIterator(2) as $row) {

            $plate = trim((string)$sheet->getCell('A' . $row->getRowIndex())->getValue());

            $date = trim((string)$sheet->getCell('C' . $row->getRowIndex())->getFormattedValue());

            if ($plate == '') {
                continue;
            }

            $newSheet->setCellValue('A' . $newRow, $plate);
            $newSheet->setCellValue('B' . $newRow, $date);

            $newRow++;
        }

        $writer = new Xlsx($newSpreadsheet);

        $writer->save($output);

        $this->info("Tayyor: {$output}");

        return self::SUCCESS;
    }
}
