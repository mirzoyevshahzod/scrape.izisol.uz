<?php

namespace App\Console\Commands;

use App\Support\UzbekPlates;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExtractUzbekPlatesCommand extends Command
{
    protected $signature = 'plates:extract {input} {output}';

    protected $description = 'Excel fayldan O\'zbekiston davlat raqamlarini ajratadi.';

    public function handle()
    {
        $input = $this->argument('input');
        $output = $this->argument('output');

        $spreadsheet = IOFactory::load($input);
        $sheet = $spreadsheet->getActiveSheet();

        $newSpreadsheet = new Spreadsheet();
        $newSheet = $newSpreadsheet->getActiveSheet();

        $newSheet->setCellValue('A1', 'Avtomobil raqami');

        $row = 2;

        // Raqam formatlari va hudud kodlari: App\Support\UzbekPlates
        foreach ($sheet->getRowIterator() as $excelRow) {

            $rowIndex = $excelRow->getRowIndex();

            $value = (string)$sheet->getCell('A'.$rowIndex)->getFormattedValue();

            foreach (UzbekPlates::extract($value) as $plate) {

                $newSheet->setCellValueExplicit('A'.$row++, $plate, DataType::TYPE_STRING);
            }
        }

        $writer = new Xlsx($newSpreadsheet);
        $writer->save($output);

        $this->info('Tayyor');
    }
}