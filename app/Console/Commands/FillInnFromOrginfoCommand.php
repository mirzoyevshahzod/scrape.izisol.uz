<?php

namespace App\Console\Commands;

use App\Models\OrginfoInn;
use App\Services\OrginfoClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Pipeline'ning yakuniy fayli: sarlavhalar, telefon (G) matn ko'rinishida va
 * INN'i (D) bo'sh qolgan kompaniyalar (E) uchun orginfo.uz'dan INN.
 *
 * INN'ning ko'pi oldingi bosqichda (scrape:zanjeer-operators) CRM'dan
 * yoziladi — bu yerda faqat qolganlari. Har bir noyob nom bir marta, 4 tadan
 * parallel so'raladi; natijalar orginfo_inns jadvalida keshlanadi (Mintrans
 * 3-bosqichi bilan umumiy). Faqat nomi aynan mos, faol tashkilot INN'i olinadi.
 */
class FillInnFromOrginfoCommand extends Command
{
    protected $signature = 'excel:fill-inn
                            {file : Excel file path}
                            {output=output.xlsx : Output file name}';

    protected $description = 'Bo\'sh INN (D) ustunini kompaniya nomi (E) bo\'yicha orginfo.uz\'dan to\'ldiradi';

    /** orginfo.uz'ga bir vaqtda yuboriladigan so'rovlar soni */
    private const CONCURRENCY = 4;

    public function handle(OrginfoClient $client): int
    {
        $spreadsheet = IOFactory::load($this->argument('file'));
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();

        for ($r = 2; $r <= $highestRow; $r++) {
            $sheet->setCellValueExplicit("G{$r}", (string) $sheet->getCell("G{$r}")->getFormattedValue(), DataType::TYPE_STRING);
        }

        foreach (['A1' => 'Chegara nomi', 'B1' => 'Davlatlar', 'C1' => 'Sana', 'D1' => 'INN', 'E1' => 'Firma nomi', 'F1' => 'Mashina raqami'] as $cell => $title) {
            $sheet->setCellValue($cell, $title);
        }

        // INN'i bo'sh qatorlar: nom => qatorlar
        $rowsByName = [];
        for ($row = 2; $row <= $highestRow; $row++) {
            $company = trim((string) $sheet->getCell("E{$row}")->getValue());

            if ($company !== '' && trim((string) $sheet->getCell("D{$row}")->getValue()) === '') {
                $rowsByName[$company][] = $row;
            }
        }

        $names = array_keys($rowsByName);
        $stats = ['found' => 0, 'not_found' => 0, 'errors' => 0, 'cached' => 0];
        $this->info('INN\'siz kompaniyalar: ' . count($names) . ' ta (' . array_sum(array_map('count', $rowsByName)) . ' qator)');

        foreach (array_chunk($names, self::CONCURRENCY) as $batch) {
            $results = [];
            $pending = [];

            foreach ($batch as $name) {
                $cached = OrginfoInn::cachedFor($name);

                if ($cached) {
                    $results[$name] = ['inn' => $cached->inn, 'cached' => true];
                } else {
                    $pending[] = $name;
                }
            }

            if ($pending) {
                foreach ($client->findInnMany($pending) as $name => $result) {
                    if (! array_key_exists('error', $result)) {
                        OrginfoInn::updateOrCreate(['name' => $name], ['inn' => $result['inn'], 'checked_at' => now()]);
                    }

                    $results[$name] = $result + ['cached' => false];
                }
            }

            foreach ($batch as $name) {
                $result = $results[$name];
                $fromCache = $result['cached'] ? ' (bazadan)' : '';

                if (array_key_exists('error', $result)) {
                    Log::error('Orginfo INN error', ['name' => $name, 'error' => $result['error']]);
                    $stats['errors']++;
                    $this->error("ERROR: {$name}");
                } elseif ($result['inn']) {
                    foreach ($rowsByName[$name] as $row) {
                        $sheet->setCellValueExplicit("D{$row}", $result['inn'], DataType::TYPE_STRING);
                    }

                    $stats['found']++;
                    $this->info("FOUND: {$name} => {$result['inn']}{$fromCache}");
                } else {
                    $stats['not_found']++;
                    $this->warn("NOT FOUND: {$name}{$fromCache}");
                }

                $stats['cached'] += $result['cached'] ? 1 : 0;
            }

            if ($pending && $name !== end($names)) {
                Sleep::usleep(1_000_000);
            }
        }

        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        (new Xlsx($spreadsheet))->save($this->argument('output'));

        $this->info("INN topildi: {$stats['found']} · topilmadi: {$stats['not_found']} · xato: {$stats['errors']}"
            . ($stats['cached'] ? " · bazadan: {$stats['cached']}" : ''));
        $this->info("DONE => {$this->argument('output')}");

        return self::SUCCESS;
    }
}
