<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ConvertEomborFilesCommand extends Command
{
    protected $signature = 'excel:convert-border
                            {inputs* : Input excel files}
                            {--output=converted.xlsx : Output excel file}';

    protected $description = 'Convert multiple border excel files to customs format';

    public function handle()
    {
        $inputs = $this->argument('inputs');
        $output = $this->option('output');

        if (empty($inputs)) {
            $this->error('Excel fayllar ko\'rsatilmagan.');

            return Command::FAILURE;
        }

        $newSpreadsheet = new Spreadsheet();
        $newSheet = $newSpreadsheet->getActiveSheet();

        $headers = [
            'Дата',
            'Транспорт рақами',
            'Брутто, кг',
            'Юк қабул қилувчи ИНН',
            'Юк қабул қилувчи',
            'Ходим',
            'Манзил пости ва етказиб бериш муддати',
        ];

        foreach ($headers as $index => $header) {
            $column = chr(65 + $index);

            $newSheet->setCellValue(
                $column . '1',
                $header
            );
        }

        $newRow = 2;

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

                for ($row = 2; $row <= $highestRow; $row++) {

                    $inn = trim(
                        (string) $sheet
                            ->getCell("G{$row}")
                            ->getValue()
                    );

                    if ($inn === '') {
                        continue;
                    }

                    $firstDigit = substr($inn, 0, 1);

                    if (!in_array($firstDigit, ['2', '3'])) {
                        continue;
                    }

                    $customsDate = $sheet
                        ->getCell("C{$row}")
                        ->getFormattedValue();

                    $transport = $sheet
                        ->getCell("E{$row}")
                        ->getValue();

                    $weight = $sheet
                        ->getCell("F{$row}")
                        ->getValue();

                    $recipient = $sheet
                        ->getCell("H{$row}")
                        ->getValue();

                    $post = $sheet
                        ->getCell("I{$row}")
                        ->getValue();

                    $newSheet->setCellValue(
                        "A{$newRow}",
                        $customsDate
                    );

                    $newSheet->setCellValue(
                        "B{$newRow}",
                        $transport
                    );

                    $newSheet->setCellValue(
                        "C{$newRow}",
                        $weight
                    );

                    $newSheet->setCellValue(
                        "D{$newRow}",
                        $inn
                    );

                    $newSheet->setCellValue(
                        "E{$newRow}",
                        $recipient
                    );

                    $newSheet->setCellValue(
                        "F{$newRow}",
                        ''
                    );

                    $newSheet->setCellValue(
                        "G{$newRow}",
                        $post
                    );

                    $newRow++;
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

        foreach (range('A', 'G') as $column) {
            $newSheet
                ->getColumnDimension($column)
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

        $totalRows = $newRow - 2;

        $this->newLine();
        $this->info('Done!');
        $this->info("Jami {$totalRows} ta ma'lumot qo'shildi.");
        $this->info("Saved: {$output}");

        return Command::SUCCESS;
    }
}