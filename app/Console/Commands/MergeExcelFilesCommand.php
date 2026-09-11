<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class MergeExcelFilesCommand extends Command
{
    protected $signature = 'excel:merge
                            {inputs* : Input excel files}
                            {--output=merged.xlsx : Output excel file}';

    protected $description = 'Bir xil formatdagi bir nechta excel faylni bitta excelga birlashtirish';

    public function handle()
    {
        $inputs = $this->argument('inputs');
        $output = $this->option('output');

        if (empty($inputs)) {
            $this->error("Excel fayllar ko'rsatilmagan.");

            return Command::FAILURE;
        }

        $newSpreadsheet = new Spreadsheet();
        $newSheet = $newSpreadsheet->getActiveSheet();

        $newRow = 1;
        $headerWritten = false;
        $totalDataRows = 0;
        $maxColumnIndex = 1;

        foreach ($inputs as $input) {

            if (!file_exists($input)) {
                $this->warn("File topilmadi: {$input}");
                continue;
            }

            $this->info("Processing: {$input}");

            try {

                $spreadsheet = IOFactory::load($input);
                $sheet = $spreadsheet->getActiveSheet();

                $highestRow = $sheet->getHighestRow();
                $highestColumnIndex = Coordinate::columnIndexFromString(
                    $sheet->getHighestColumn()
                );

                $maxColumnIndex = max($maxColumnIndex, $highestColumnIndex);

                /*
                 * Sarlavhani faqat birinchi fayldan bir marta yozamiz
                 */
                if (!$headerWritten) {

                    for ($col = 1; $col <= $highestColumnIndex; $col++) {

                        $columnLetter = Coordinate::stringFromColumnIndex($col);

                        $newSheet->setCellValue(
                            $columnLetter . $newRow,
                            $sheet->getCell($columnLetter . '1')->getValue()
                        );
                    }

                    $newRow++;
                    $headerWritten = true;
                }

                /*
                 * Har bir faylning 1-qatori sarlavha hisoblanadi
                 * va o'tkazib yuboriladi
                 */
                for ($row = 2; $row <= $highestRow; $row++) {

                    $rowData = [];
                    $isRowEmpty = true;

                    for ($col = 1; $col <= $highestColumnIndex; $col++) {

                        $columnLetter = Coordinate::stringFromColumnIndex($col);

                        $value = $sheet->getCell($columnLetter . $row)->getValue();

                        $rowData[] = $value;

                        if ($value !== null && $value !== '') {
                            $isRowEmpty = false;
                        }
                    }

                    if ($isRowEmpty) {
                        continue;
                    }

                    foreach ($rowData as $index => $value) {

                        $columnLetter = Coordinate::stringFromColumnIndex($index + 1);

                        $newSheet->setCellValue(
                            $columnLetter . $newRow,
                            $value
                        );
                    }

                    $newRow++;
                    $totalDataRows++;
                }

                /*
                 * MUHIM:
                 * Faylni xotiradan chiqaramiz
                 */
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);

                gc_collect_cycles();

            } catch (\Throwable $e) {

                $this->error("Xatolik: {$input}");
                $this->error($e->getMessage());

                return Command::FAILURE;
            }
        }

        if (!$headerWritten) {
            $this->error("Hech qanday ma'lumot o'qib bo'lmadi.");

            return Command::FAILURE;
        }

        for ($col = 1; $col <= $maxColumnIndex; $col++) {

            $columnLetter = Coordinate::stringFromColumnIndex($col);

            $newSheet
                ->getColumnDimension($columnLetter)
                ->setAutoSize(true);
        }

        try {

            $writer = new Xlsx($newSpreadsheet);

            $writer->save($output);

            $newSpreadsheet->disconnectWorksheets();
            unset($newSpreadsheet);

            gc_collect_cycles();

        } catch (\Throwable $e) {

            $this->error('Output Excel yaratishda xatolik:');
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        $this->newLine();
        $this->info('Done!');
        $this->info("Jami {$totalDataRows} ta ma'lumot birlashtirildi.");
        $this->info("Saved: {$output}");

        return Command::SUCCESS;
    }
}
