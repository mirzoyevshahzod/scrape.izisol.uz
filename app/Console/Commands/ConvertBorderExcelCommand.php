<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;

class ConvertBorderExcelCommand extends Command
{
    protected $signature = 'excel:border-convert
                            {input : Input excel file}
                            {output : Output excel file}';

    protected $description = 'Convert border excel format';

    public function handle()
    {
        $input = $this->argument('input');
        $output = $this->argument('output');

        if (!file_exists($input)) {
            $this->error("File not found: {$input}");
            return Command::FAILURE;
        }

        $spreadsheet = IOFactory::load($input);
        $sheet = $spreadsheet->getActiveSheet();

        $newSpreadsheet = new Spreadsheet();
        $newSheet = $newSpreadsheet->getActiveSheet();

        $newSheet->setCellValue('A1', 'Avtomobil raqami');
        $newSheet->setCellValue('B1', 'Sana');

        $row = 2;

        foreach ($sheet->getRowIterator(2) as $excelRow) {

            $index = $excelRow->getRowIndex();

            $carNumber = trim((string)$sheet->getCell("C{$index}")->getValue());
            $date = trim((string)$sheet->getCell("D{$index}")->getFormattedValue());

            if ($carNumber == '') {
                continue;
            }

            try {
                $date = Carbon::createFromFormat(
                    'd.m.Y H:i:s',
                    $date
                )->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                $date = null;
            }

            $newSheet->setCellValue("A{$row}", $carNumber);
            $newSheet->setCellValue("B{$row}", $date);

            $row++;
        }

        $writer = new Xlsx($newSpreadsheet);
        $writer->save($output);

        $this->info("Created: {$output}");

        return Command::SUCCESS;
    }
}