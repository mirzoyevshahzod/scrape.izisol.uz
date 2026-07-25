<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
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

        $regions = [
            '01','10','20','25','30',
            '40','50','60','70','80',
            '85','95'
        ];

        $regionPattern = implode('|', $regions);

        // 10174BBB yoki 10B174BB
        $pattern = '/\b(?:'
            . '(?:' . $regionPattern . ')[0-9]{3}[A-Z]{3}'
            . '|'
            . '(?:' . $regionPattern . ')[A-Z][0-9]{3}[A-Z]{2}'
            . ')\b/i';

        foreach ($sheet->getRowIterator() as $excelRow) {

            $rowIndex = $excelRow->getRowIndex();

            $value = (string)$sheet->getCell('A'.$rowIndex)->getFormattedValue();

            if (preg_match_all($pattern, strtoupper($value), $matches)) {

                foreach ($matches[0] as $plate) {

                    $newSheet->setCellValue('A'.$row++, $plate);
                }
            }
        }

        $writer = new Xlsx($newSpreadsheet);
        $writer->save($output);

        $this->info('Tayyor');
    }
}