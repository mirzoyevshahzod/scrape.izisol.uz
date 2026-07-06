<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Illuminate\Support\Str;

class QoldauScrapeCommand extends Command
{
    protected $signature = 'qoldau:scrape {checkpoint}';

    protected $description = 'Scrape qoldau scoreboard dynamically';

    public function handle()
    {
        set_time_limit(0);

        $checkpointKey = $this->argument('checkpoint');

        $groups = $this->getCheckpointGroups();

        // Agar group bo‘lsa array qaytadi
        // Agar oddiy checkpoint bo‘lsa bitta array bo‘ladi
        $checkpoints = $groups[$checkpointKey] ?? [$checkpointKey];

        // EXCEL
        $spreadsheet = new Spreadsheet();

        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'A1' => 'Chegara nomi',
            'B1' => 'Mashina raqami',
            'C1' => 'Sana va vaqt',
            'D1' => 'Status',
            'E1' => 'States',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        $sheet->getStyle('A1:E1')->getFont()->setBold(true);

        $rowNumber = 2;

        // HAMMA CHECKPOINTLARNI AYLANADI
        foreach ($checkpoints as $checkpoint) {

            $checkpointName = $this->getCheckpointName($checkpoint);

            $this->info("Scraping: $checkpointName");

            $lastPage = (int) $this->detectLastPage($checkpoint);

            for ($page = 1; $page <= $lastPage; $page++) {

                $this->info("Page: $page");

                $response = Http::get(
                    'https://cgr.qoldau.kz/ru/registry/scoreboard',
                    [
                        'flTruckNumber' => '',
                        'oq' => '',
                        'flCheckpoint' => $checkpoint,
                        'p' => $page
                    ]
                );

                if (!$response->successful()) {
                    $this->error("Failed page $page");
                    continue;
                }

                $crawler = new Crawler($response->body());

                $rows = $crawler->filter('.row.border-bottom.py-2');

                $rows->each(function (Crawler $row) use (&$rowNumber, $sheet) {

                    $checkpoint = $row
                        ->filter('.font-weight-bold.font-16')
                        ->first()
                        ->text('');

                    $plate = trim(
                        $row->filter('.number-plate .font-weight-bold')
                            ->text('')
                    );

                    $cols = $row->filter('.col-md-6');

                    $rightBlock = $cols->eq(1);

                    $date = $rightBlock
                        ->filter('span.font-weight-bold')
                        ->eq(0)
                        ->text('');

                    $time = $rightBlock
                        ->filter('.col-6.text-left span')
                        ->last()
                        ->text('');

                    $status = $row->filter('.badge')->text('');

                    // Uzbekistan plate format
                    $uzPattern = '/^(\d{2}\s?[A-Z]?\s?\d{3}\s?[A-Z]{1,3}|T\s?\d{3}\s?[A-Z]{1,2})$/';

                    if (!preg_match($uzPattern, $plate)) {
                        return;
                    }

                    $sheet->setCellValue("A$rowNumber", trim($checkpoint));
                    $sheet->setCellValue("B$rowNumber", $plate);
                    $sheet->setCellValue("C$rowNumber", trim($date . ' ' . $time));
                    $sheet->setCellValue("D$rowNumber", trim($status));
                    $sheet->setCellValue("E$rowNumber", 'Казахстан Россия');
                    $rowNumber++;
                });

                sleep(1);
            }
        }

        // COLUMN SIZE
        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // FILE NAME
        $groups = array_keys($this->getCheckpointGroups());

        if (in_array($checkpointKey, $groups)) {

            // GROUP
            $safeName = Str::slug($checkpointKey);

        } else {

            // SINGLE
            $checkpointName = $this->getCheckpointName($checkpointKey);

            $safeName = Str::slug($checkpointName);
        }

        $dateNow = Carbon::now()
            ->format('Y-m-d_H-i-s');

        $filePath = storage_path("app/qozoq/{$safeName}-{$dateNow}.xlsx");

        $writer = new Xlsx($spreadsheet);

        $writer->save($filePath);

        $this->info("Saved: $filePath");
    }

    private function detectLastPage($checkpoint)
    {
        $response = Http::get(
            'https://cgr.qoldau.kz/ru/registry/scoreboard',
            [
                'flCheckpoint' => $checkpoint,
                'p' => 1
            ]
        );

        if (!$response->successful()) {
            return 1;
        }

        $crawler = new Crawler($response->body());

        $pages = $crawler->filter('.pagination .page-link');

        $lastPage = 1;

        $pages->each(function (Crawler $node) use (&$lastPage) {

            $text = trim($node->text());

            if (is_numeric($text)) {
                $lastPage = max($lastPage, (int) $text);
            }
        });

        return $lastPage;
    }

    public function getCheckpointName($checkpoint)
    {
        $map = [

            // Russia
            '238304120665000000' => 'Акбалшык - Воскресенское',
            '238245356822000000' => 'Аксай – Илек',
            '238238650340000000' => 'Алимбет – Орск',
            '238309634433000000' => 'Амангельды – Невольное',
            '238306291664000000' => 'Ауыл – Веселоярск',
            '238304557899000000' => 'Аят - Николаевка',
            '238316180166000000' => 'Бидаик – Одесское',
            '238239148521000000' => 'Жайсан – Сагарчин',
            '238316459162000000' => 'Жана Жол – Петухово',
            '238246015768000000' => 'Жаныбек – Вишневка',
            '238307181634000000' => 'Жезкент – Горняк',
            '238304850217000000' => 'Желкуар – Мариинский',
            '238316732771000000' => 'Каракога – Исилькуль',
            '238239515479000000' => 'Карашатау - Светлый',
            '238311401124000000' => 'Косак – Павловка',
            '238308566679000000' => 'Коянбай – Малиновое Озеро',
            '238243101359000000' => 'Курмангазы – Караузек',
            '238316951727000000' => 'Кызыл Жар – Казанское',
            '238315911519000000' => 'Найза – Павловка (Славгород)',
            '238246712805000000' => 'Орда – Полынный',
            '238247045484000000' => 'Сырым – Маштаково',
            '238303161413000000' => 'Таскала – Озинки',
            '238305660457000000' => 'Убаган – Звериноголовское',
            '238244498776000000' => 'Убе – Михайловка',
            '238315711818000000' => 'Урлютобе – Ольховка',
            '238303383491000000' => 'Шаган – Теплое',
            '238309028892000000' => 'Шарбакты – Кулунда',

            // Uzbekistan
            '234422898551000000' => 'Атамекен - Гулистан',
            '231576795648000000' => 'Б. Конысбаева - Яллама',
            '234531528148000000' => 'Казыгурт - Майский',
            '238236183776000000' => 'Капланбек - Навои',
            '224752846845000000' => 'Тажен - Каракалпакстан',

            // Turkmenistan
            '224752450232000000' => 'Темир-Баба - Карабугаз',

            // China
            '224749863825000000' => 'Бахты - Покиту',
            '215778822067000000' => 'Достык - Алашанькоу',
            '222979531669000000' => 'Калжат - Дулаты',
            '224751327844000000' => 'Майкапчагай - Зимунай',
            '222978891854000000' => 'Нур Жолы - Хоргос',

            // Kyrgyzstan
            '291817455346000000' => 'Айша-Биби - Чон-Какпа',
            '291818150184000000' => 'Аухатты - Кенбулын',
            '291820603631000000' => 'Кеген - Каркыра',
            '291819404994000000' => 'Кордай - Ак-Жол',
            '291821145135000000' => 'Сартобе - Токмок',
            '291818866418000000' => 'Сыпатай Батыр - Чалдыбар',

            // Kazakhstan
            '314889163298000000' => 'Порт Курык'
        ];

        return $map[$checkpoint] ?? $checkpoint;
    }

    private function getCheckpointGroups()
    {
        return [

            'Kazakhstan - Russia' => [
                '238304120665000000',
                '238245356822000000',
                '238238650340000000',
                '238309634433000000',
                '238306291664000000',
                '238304557899000000',
                '238316180166000000',
                '238239148521000000',
                '238316459162000000',
                '238246015768000000',
                '238307181634000000',
                '238304850217000000',
                '238316732771000000',
                '238239515479000000',
                '238311401124000000',
                '238308566679000000',
                '238243101359000000',
                '238316951727000000',
                '238315911519000000',
                '238246712805000000',
                '238247045484000000',
                '238303161413000000',
                '238305660457000000',
                '238244498776000000',
                '238315711818000000',
                '238303383491000000',
                '238309028892000000',
            ],

            'Kazakhstan - Uzbekistan' => [
                '234422898551000000',
                '231576795648000000',
                '234531528148000000',
                '238236183776000000',
                '224752846845000000',
            ],

            'Kazakhstan - Turkmenistan' => [
                '224752450232000000',
            ],

            'Kazakhstan - China' => [
                '224749863825000000',
                '215778822067000000',
                '222979531669000000',
                '224751327844000000',
                '222978891854000000',
            ],

            'Kazakhstan - Kyrgyzstan' => [
                '291817455346000000',
                '291818150184000000',
                '291820603631000000',
                '291819404994000000',
                '291821145135000000',
                '291818866418000000',
            ],

            'Kazakhstan' => [
                '314889163298000000',
            ]
        ];
    }
}