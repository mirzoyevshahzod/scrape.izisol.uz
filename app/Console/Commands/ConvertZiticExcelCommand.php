<?php

namespace App\Console\Commands;

use Carbon\Carbon;
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

        if ($sheet->getHighestRow() >= 2) {
            foreach ($sheet->getRowIterator(2) as $row) {

                $plate = trim((string) $sheet->getCell('A' . $row->getRowIndex())->getValue());

                $date = trim((string) $sheet->getCell('C' . $row->getRowIndex())->getFormattedValue());

                if ($plate == '') {
                    continue;
                }

                // Manba (zitic.ru) faqat qisqa sanani beradi, vaqtsiz
                // ("23.09.26"). Buni to'liq "Y-m-d H:i:s" formatiga
                // o'tkazamiz — aks holda Zanjeer CRM bu qisqa formatni
                // sana sifatida tanimay, import kunini sana qilib qo'yadi
                // (va qatordagi raqamlarni vaqt sifatida noto'g'ri o'qiydi).
                try {
                    $date = Carbon::createFromFormat('d.m.y', $date)
                        ->startOfDay()
                        ->format('Y-m-d H:i:s');
                } catch (\Throwable $e) {
                    $date = null;
                }

                $newSheet->setCellValue('A' . $newRow, $plate);
                $newSheet->setCellValue('B' . $newRow, $date);

                $newRow++;
            }
        }

        $writer = new Xlsx($newSpreadsheet);

        $writer->save($output);

        $this->info("Tayyor: {$output}");

        return self::SUCCESS;
    }
}
