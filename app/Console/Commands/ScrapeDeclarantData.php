<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\DomCrawler\Crawler;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use App\Models\Auto;

class ScrapeDeclarantData extends Command
{
    protected $signature = 'scrape:declarant-data {zone}';

    protected $description =
        'Scrape Belarus queue and export Uzbek cars';

    private array $zones = [

        'benyakoni' =>
            'https://mon.declarant.by/zone/benyakoni',

        'brest-bts' =>
            'https://mon.declarant.by/zone/brest-bts',

        'grigorovschina' =>
            'https://mon.declarant.by/zone/grigorovschina',

        'kamennii-log' =>
            'https://mon.declarant.by/zone/kamennii-log',

        'kozlovichi' =>
            'https://mon.declarant.by/zone/kozlovichi',
    ];

    private array $uzbekPatterns = [
        '/^(01|10|20|25|30|40|50|60|70|75|80|85|90|95)[A-Z]\d{3}[A-Z]{2}$/',
        '/^(01|10|20|25|30|40|50|60|70|75|80|85|90|95)\d{3}[A-Z]{3}$/',
        '/^(01|10|20|25|30|40|50|60|70|75|80|85|90|95)\d{4}[A-Z]{2}$/',
    ];

    public function handle()
    {
        $zone = $this->argument('zone');

        if (!isset($this->zones[$zone])) {

            $this->error('Invalid zone');

            return;
        }

        $url = $this->zones[$zone];

        $this->info("Opening: {$url}");

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0',
        ])->get($url);

        if (!$response->successful()) {

            $this->error('Website not opened');

            return;
        }

        $html = $response->body();

        $crawler = new Crawler($html);

        $spreadsheet = new Spreadsheet();

        $sheet =
            $spreadsheet->getActiveSheet();

        $headers = [
            'Порядок вызова',
            'Тип очереди',
            'Рег.номер',
            'Дата регистрации в ЗО',
            'Статус изменен',
            'Статус',
            'Company Name',
            'Phones',
            'States',
            'Border'
        ];

        $columns =
            ['A','B','C','D','E','F','G','H', 'I', 'J'];

        foreach ($headers as $i => $header) {

            $sheet->setCellValue(
                $columns[$i] . '1',
                $header
            );
        }

        $rowIndex = 2;

        $crawler
        ->filter('tbody tr')
        ->each(function ($tr)
        use ($sheet, &$rowIndex, $zone) {

            $tds = $tr->filter('td');

            if ($tds->count() < 6) {
                return;
            }

            $queueType =
                trim($tds->eq(1)->text());

            $regnum = strtoupper(
                preg_replace(
                    '/[^A-Z0-9]/',
                    '',
                    $tds->eq(2)->text()
                )
            );

            if (
                !$this->isUzbekVehicle($regnum)
            ) {
                return;
            }

            $registrationDate =
                trim($tds->eq(3)->text());

            $changedDate =
                trim($tds->eq(4)->text());

            $status =
                trim($tds->eq(5)->text());

            $auto =
                Auto::where(
                    'state_number',
                    $regnum
                )->first();

            $company =
                $auto->company_name ?? '';

            $phone =
                $auto->phone ?? '';

            $sheet->setCellValue(
                "A{$rowIndex}",
                trim($tds->eq(0)->text())
            );

            $sheet->setCellValue(
                "B{$rowIndex}",
                $queueType
            );

            $sheet->setCellValue(
                "C{$rowIndex}",
                $regnum
            );

            $sheet->setCellValue(
                "D{$rowIndex}",
                $registrationDate
            );

            $sheet->setCellValue(
                "E{$rowIndex}",
                $changedDate
            );

            $sheet->setCellValue(
                "F{$rowIndex}",
                $status
            );

            $sheet->setCellValue(
                "G{$rowIndex}",
                $company
            );

            $sheet->setCellValueExplicit(
                "G{$rowIndex}",
                (string) $phone,
                DataType::TYPE_STRING
            );

            $sheet->setCellValue(
                "I{$rowIndex}",
                'Беларусь Литва'
            );
            $sheet->setCellValue(
            "J{$rowIndex}",
            match ($zone) {
                'benyakoni'      => 'Бенякони',
                'brest-bts'      => 'Брест-БТС',
                'grigorovschina' => 'Григорьевщина',
                'kamennii-log'   => 'Каменный Лог',
                'kozlovichi'     => 'Козловичи',
                default          => $zone,
            }
        );
            $rowIndex++;
        });

        $fileName =
            $zone . '-' .
            now()->format('Y-m-d-H-i-s') .
            '.xlsx';

        $path =
            storage_path(
                "app/declarant/{$fileName}"
            );

        (new Xlsx($spreadsheet))
            ->save($path);

        $this->info(
            "Excel saved: {$path}"
        );
    }

    private function isUzbekVehicle(string $reg): bool
    {
        foreach (
            $this->uzbekPatterns
            as $pattern
        ) {

            if (preg_match($pattern, $reg)) {

                return true;
            }
        }

        return false;
    }
}