<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use App\Models\Auto;

class ScrapeDeclarantData extends Command
{
    protected $signature = 'scrape:declarant-data {zone}';

    protected $description =
        'Scrape Belarus queue and export Uzbek cars';

    private array $zones = [
        'benyakoni' => [
            'checkpoint_id' => '53d94097-2b34-11ec-8467-ac1f6bf889c0',
            'name' => 'Бенякони',
        ],

        'kamennii-log' => [
            'checkpoint_id' => 'b60677d4-8a00-4f93-a781-e129e1692a03',
            'name' => 'Каменный Лог',
        ],
        'kozlovichi' => [
            'checkpoint_id' => '98b5be92-d3a5-4ba2-9106-76eb4eb3df49',
            'name' => 'Козловичи',
        ],
    ];

    private array $uzbekPatterns = [
        '/^(01|10|20|25|30|40|50|60|70|75|80|85|90|95)[A-Z]\d{3}[A-Z]{2}$/',
        '/^(01|10|20|25|30|40|50|60|70|75|80|85|90|95)\d{3}[A-Z]{3}$/',
        '/^(01|10|20|25|30|40|50|60|70|75|80|85|90|95)\d{4}[A-Z]{2}$/',
    ];

    public function handle()
    {
        $zone = $this->argument('zone');

        if (! isset($this->zones[$zone])) {
            $this->error('Invalid zone');
            return;
        }

        $checkpointId = $this->zones[$zone]['checkpoint_id'];

        $response = Http::acceptJson()->get(
            'https://belarusborder.by/info/monitoring-new',
            [
                'token' => 'test',
                'checkpointId' => $checkpointId,
            ]
        );

        if (! $response->successful()) {
            $this->error('API request failed');
            return;
        }

        $data = $response->json();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Порядок вызова',
            'Тип очереди',
            'Рег.номер',
            'Дата регистрации в ЗО',
            'Статус изменен',
            'Статус',
            'Company Name',
            'Phone',
            'State',
            'Border',
        ];

        $columns = ['A','B','C','D','E','F','G','H','I','J'];

        foreach ($headers as $i => $header) {
            $sheet->setCellValue($columns[$i] . '1', $header);
        }

        $statuses = [
            1 => 'В очереди',
            2 => 'Прибыл в ЗО',
            3 => 'Вызван в ПП',
        ];

        $queueTypes = [
            1 => 'Приоритет',
            2 => 'Электронная очередь',
            3 => 'Живая очередь',
        ];

        $rowIndex = 2;

        foreach ($data['truckLiveQueue'] ?? [] as $item) {

            $regnum = strtoupper(
                preg_replace('/[^A-Z0-9]/', '', $item['regnum'])
            );

            $this->info("Processing: {$regnum}");

            if (! $this->isUzbekVehicle($regnum)) {
                continue;
            }

            $auto = Auto::where('state_number', $regnum)->first();

            $sheet->setCellValue(
                "A{$rowIndex}",
                $item['order_id'] ?? '-'
            );

            $sheet->setCellValue(
                "B{$rowIndex}",
                $queueTypes[$item['type_queue']] ?? $item['type_queue']
            );

            $sheet->setCellValue(
                "C{$rowIndex}",
                $regnum
            );

            $sheet->setCellValue(
                "D{$rowIndex}",
                $item['registration_date']
            );

            $sheet->setCellValue(
                "E{$rowIndex}",
                $item['changed_date']
            );

            $sheet->setCellValue(
                "F{$rowIndex}",
                $statuses[$item['status']] ?? $item['status']
            );

            $sheet->setCellValue(
                "G{$rowIndex}",
                $auto->company_name ?? ''
            );

            $sheet->setCellValueExplicit(
                "H{$rowIndex}",
                (string)($auto->phone ?? ''),
                DataType::TYPE_STRING
            );

            $sheet->setCellValue(
                "I{$rowIndex}",
                'Беларусь Литва'
            );

            $sheet->setCellValue(
                "J{$rowIndex}",
                $this->zones[$zone]['name']
            );

            $rowIndex++;
        }

        $fileName = $zone . '-' . now()->format('Y-m-d-H-i-s') . '.xlsx';

        $path = storage_path("app/declarant/{$fileName}");

        (new Xlsx($spreadsheet))->save($path);

        $this->info("Excel saved: {$path}");
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