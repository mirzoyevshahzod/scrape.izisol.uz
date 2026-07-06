<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use ZipArchive;

class SplitBorderExcelCommand extends Command
{
    protected $signature = 'excel:split {file}';
    protected $description = 'Split excel by border';

    public function handle()
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return Command::FAILURE;
        }

        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();

        $rows = $sheet->toArray();

        if (count($rows) < 2) {
            $this->error('Excel is empty.');
            return Command::FAILURE;
        }

        // Header
        $header = array_shift($rows);

        $borderIndex = array_search('Chegara nomi', $header);
        $carIndex    = array_search('Mashina raqami', $header);
        $dateIndex   = array_search('Sana va vaqt', $header);

        if ($borderIndex === false || $carIndex === false || $dateIndex === false) {
            $this->error('Required columns not found.');
            return Command::FAILURE;
        }

        $groups = [];

        foreach ($rows as $row) {

            if (empty($row[$borderIndex])) {
                continue;
            }

            $groups[$row[$borderIndex]][] = [
                'car'  => $row[$carIndex],
                'date' => $row[$dateIndex],
            ];
        }

        $outputDir = storage_path('app/exports');

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        foreach ($groups as $border => $items) {

            $newSpreadsheet = new Spreadsheet();
            $newSheet = $newSpreadsheet->getActiveSheet();

            $newSheet->setCellValue('A1', 'Avtomobil raqami');
            $newSheet->setCellValue('B1', 'Sana');

            $rowNumber = 2;

            foreach ($items as $item) {
                $newSheet->setCellValue('A' . $rowNumber, $item['car']);
                $newSheet->setCellValue('B' . $rowNumber, $item['date']);
                $rowNumber++;
            }

            // Windows uchun taqiqlangan belgilarni almashtirish
            $fileName = preg_replace('/[\\\\\/:*?"<>|]/u', '_', trim($border));

            $fileName .= '_' . Carbon::today()->format('Y-m-d') . '.xlsx';

            $writer = new Xlsx($newSpreadsheet);
            $writer->save($outputDir . DIRECTORY_SEPARATOR . $fileName);

            $this->info("Created: {$fileName}");
        }

        $originalName = pathinfo($file, PATHINFO_FILENAME);

        $zipName = $originalName . '.zip';
        $zipPath = $outputDir . DIRECTORY_SEPARATOR . $zipName;
        $zipPath = $outputDir . DIRECTORY_SEPARATOR . $zipName;

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {

            foreach (glob($outputDir . DIRECTORY_SEPARATOR . '*.xlsx') as $excelFile) {

                $zip->addFile(
                    $excelFile,
                    basename($excelFile)
                );
            }

            $zip->close();

            $this->info("ZIP created: {$zipPath}");
        } else {
            $this->error("ZIP yaratib bo'lmadi.");
        }

        foreach (glob($outputDir . DIRECTORY_SEPARATOR . '*.xlsx') as $excelFile) {
            unlink($excelFile);
        }

       $this->info("ZIP created: {$zipPath}");

        return Command::SUCCESS;
    }
}