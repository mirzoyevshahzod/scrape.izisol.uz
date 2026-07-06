<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class NormalizeBordersExcelCommand extends Command
{
    protected $signature = 'excel:normalize 
                            {output : Output file name}
                            {files* : Excel files}';

    protected $description = 'Normalize different excel formats into one format';

   public function handle()
{
    $files = $this->argument('files');

    // storage/app/merge
    $mergePath = storage_path('app/merge');

    if (!file_exists($mergePath)) {
        mkdir($mergePath, 0777, true);
    }

    $output = $mergePath . '/' . $this->argument('output');

    $normalized = [];

    foreach ($files as $file) {

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            continue;
        }

        $this->info("Reading: {$file}");

        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        if (count($rows) < 2) {
            continue;
        }

        $headers = array_map(fn($h) => trim((string)$h), $rows[0]);

        foreach (array_slice($rows, 1) as $row) {

            $assoc = [];

            foreach ($headers as $index => $header) {
                $assoc[$header] = $row[$index] ?? null;
            }

            $normalized[] = [
                'Chegara Nomi' => $this->findValue($assoc, [
                    'Chegara nomi',
                    'Чегара номи',
                    'Chegara Nomi',
                    'Border',
                    'Kirish joyi',
                ]),

                'Davlatlar' => $this->findValue($assoc, [
                    'States',
                    'Davlatlar',
                ]),

                'Sana' => $this->findValue($assoc, [
                    'Sana va vaqt',
                    'Date',
                    'Дата регистрации в ЗО',
                    'Sana',
                ]),

                'INN' => '',

                'Firma nomi' => $this->findValue($assoc, [
                    'Company',
                    'Company Name',
                    'Фирма номи',
                ]),

                'Mashina raqami' => $this->findValue($assoc, [
                    'Mashina raqami',
                    'Plate',
                    'Рег.номер',
                    'Avtomobil raqami',
                    'Машина рақами',
                ]),

                'Telefon raqami' => $this->findValue($assoc, [
                    'Telefon',
                    'Phone',
                    'Phones',
                    'Телефон рақами',
                ]),

                'Meneger Ismi sharifi' => '',
            ];
        }
    }

    $this->saveExcel($normalized, $output);

    $this->info("DONE => {$output}");
}

    private function findValue(array $assoc, array $keys)
    {
        foreach ($keys as $key) {
            if (isset($assoc[$key]) && $assoc[$key] !== '') {
                return $assoc[$key];
            }
        }

        return '';
    }

    private function saveExcel(array $data, string $output)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Chegara Nomi',
            'Davlatlar',
            'Sana',
            'INN',
            'Firma nomi',
            'Mashina raqami',
            'Telefon raqami',
            'Meneger Ismi sharifi',
        ];

        $sheet->fromArray($headers, null, 'A1');

        $rowNumber = 2;

        foreach ($data as $row) {
            $sheet->fromArray(array_values($row), null, 'A' . $rowNumber);
            $rowNumber++;
        }

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($output);
    }
}