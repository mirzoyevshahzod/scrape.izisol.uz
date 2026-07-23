<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ConvertDeclarantExcelCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'declarant:convert {input} {output}';

    protected $description = 'Declarant Excel faylini import formatiga o‘tkazadi.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $input = $this->argument('input');
        $output = $this->argument('output');

        $spreadsheet = IOFactory::load($input);
        $sheet = $spreadsheet->getActiveSheet();

        $newSpreadsheet = new Spreadsheet();
        $newSheet = $newSpreadsheet->getActiveSheet();

        // Header
        $newSheet->setCellValue('A1', 'Avtomobil raqami');
        $newSheet->setCellValue('B1', 'Sana');

        $newRow = 2;

        foreach ($sheet->getRowIterator(2) as $row) {

            $rowIndex = $row->getRowIndex();

            // C ustun - Registratsiya raqami
            $plate = trim((string) $sheet->getCell('C' . $rowIndex)->getValue());

            // D ustun - Sana
            $date = trim((string) $sheet->getCell('D' . $rowIndex)->getFormattedValue());

            if ($plate === '') {
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
