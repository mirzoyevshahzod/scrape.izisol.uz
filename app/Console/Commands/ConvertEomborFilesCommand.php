<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ConvertEomborFilesCommand extends Command
{
    protected $signature = 'excel:convert-border
                            {input : Input excel file}
                            {output? : Output excel file}';

    protected $description = 'Convert border excel to customs format';

    public function handle()
    {
        $input = $this->argument('input');
        $output = $this->argument('output') ?? 'converted.xlsx';

        if (!file_exists($input)) {
            $this->error("File not found: {$input}");
            return Command::FAILURE;
        }

        $spreadsheet = IOFactory::load($input);
        $sheet = $spreadsheet->getActiveSheet();

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
            $newSheet->setCellValue($column . '1', $header);
        }

        $newRow = 2;

        $highestRow = $sheet->getHighestRow();

        for ($row = 2; $row <= $highestRow; $row++) {

            $inn = trim((string)$sheet->getCell("G{$row}")->getValue());

            if ($inn == '') {
                continue;
            }

            $firstDigit = substr($inn, 0, 1);

            // faqat 2 va 3 bilan boshlangani qolsin
            if (!in_array($firstDigit, ['2', '3'])) {
                continue;
            }

            $customsDate = $sheet->getCell("C{$row}")->getFormattedValue();
            $transport   = $sheet->getCell("E{$row}")->getValue();
            $weight      = $sheet->getCell("F{$row}")->getValue();
            $recipient   = $sheet->getCell("H{$row}")->getValue();
            $post        = $sheet->getCell("I{$row}")->getValue();

            $newSheet->setCellValue("A{$newRow}", $customsDate);
            $newSheet->setCellValue("B{$newRow}", $transport);
            $newSheet->setCellValue("C{$newRow}", $weight);
            $newSheet->setCellValue("D{$newRow}", $inn);
            $newSheet->setCellValue("E{$newRow}", $recipient);
            $newSheet->setCellValue("F{$newRow}", '');
            $newSheet->setCellValue("G{$newRow}", $post);

            $newRow++;
        }

        foreach (range('A', 'G') as $column) {
            $newSheet->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = new Xlsx($newSpreadsheet);
        $writer->save($output);

        $this->info("Done!");
        $this->info("Saved: {$output}");

        return Command::SUCCESS;
    }
}
