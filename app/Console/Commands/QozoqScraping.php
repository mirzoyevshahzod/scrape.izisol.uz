<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class QozoqScraping extends Command
{
    protected $signature = 'qozoq:scrape';

    protected $description = 'Scrape all Qozoq checkpoints and send all vehicle records to API';

    private string $apiUrl =
        'https://358b-93-188-81-10.ngrok-free.app/api/checkpoints/queue-records';

    public function handle()
    {
        set_time_limit(0);

        $this->info('Qozoq scraping boshlandi...');

        $groups = $this->getCheckpointGroups();

        $totalFound = 0;
        $totalSent = 0;

        /*
         * Barcha group ichidagi checkpointlarni yig'amiz.
         * Bir xil checkpoint qayta takrorlanmasligi uchun unique qilamiz.
         */
        $checkpoints = collect($groups)
            ->flatten()
            ->unique()
            ->values()
            ->all();

        $this->info('Jami checkpoint: ' . count($checkpoints));

        foreach ($checkpoints as $checkpoint) {

            $checkpointName = $this->getCheckpointName($checkpoint);

            $this->newLine();
            $this->info("========================================");
            $this->info("Checkpoint: {$checkpointName}");
            $this->info("External ID: {$checkpoint}");
            $this->info("========================================");

            $lastPage = $this->detectLastPage($checkpoint);

            $this->info("Jami page: {$lastPage}");

            for ($page = 1; $page <= $lastPage; $page++) {

                $this->info("Page {$page}/{$lastPage}");

                try {

                    $response = Http::timeout(60)
                        ->retry(3, 1000)
                        ->get(
                            'https://cgr.qoldau.kz/ru/registry/scoreboard',
                            [
                                'flTruckNumber' => '',
                                'oq' => '',
                                'flCheckpoint' => $checkpoint,
                                'p' => $page,
                            ]
                        );

                } catch (\Throwable $e) {

                    $this->error(
                        "Page {$page} olishda xato: " . $e->getMessage()
                    );

                    continue;
                }

                if (!$response->successful()) {

                    $this->error(
                        "Failed page {$page}. HTTP: {$response->status()}"
                    );

                    continue;
                }

                $crawler = new Crawler($response->body());

                $rows = $crawler->filter('.row.border-bottom.py-2');

                if ($rows->count() === 0) {

                    $this->warn("Page {$page}: record topilmadi.");

                    continue;
                }

                $records = [];

                $rows->each(function (Crawler $row) use (&$records, $checkpoint, $checkpointName, &$totalFound) {

                    try {

                        /*
                         * Mashina raqami
                         *
                         * MUHIM:
                         * Bu yerda endi O'zbekiston pattern tekshirilmaydi.
                         * Qoldau'dan qanday raqam kelsa, shuni olamiz.
                         */

                        $plate = trim(
                            $row
                                ->filter('.number-plate .font-weight-bold')
                                ->text('')
                        );

                        if ($plate === '') {
                            return;
                        }

                        /*
                         * Sana
                         */
                        $cols = $row->filter('.col-md-6');

                        $rightBlock = $cols->eq(1);

                        $date = trim(
                            $rightBlock
                                ->filter('span.font-weight-bold')
                                ->eq(0)
                                ->text('')
                        );

                        /*
                         * Vaqt
                         */
                        $time = trim(
                            $rightBlock
                                ->filter('.col-6.text-left span')
                                ->last()
                                ->text('')
                        );

                        /*
                         * Status
                         */
                        $status = trim(
                            $row
                                ->filter('.badge')
                                ->text('')
                        );

                        /*
                         * Sana + vaqtni Carbon orqali parse qilamiz.
                         */
                        $dateTime = $this->parseDateTime($date, $time);

                        if (!$dateTime) {

                            $this->warn(
                                "Sana parse bo'lmadi: {$date} {$time}, plate: {$plate}"
                            );

                            return;
                        }

                        /*
                         * API talab qilayotgan format:
                         *
                         * record_date       => Y-m-d
                         * time_slot_start   => H:i
                         * time_slot_end     => nullable
                         */


                        $statusText = trim($row->filter('.badge')->text(''));

                        $statusMap = [
                            'В очереди' => 'in_queue',
                            'Пересёк пункт пропуска' => 'passed',
                        ];

                        $status = $statusMap[$statusText] ?? null;

                        if ($status === null) {
                            $this->warn("Noma'lum status: {$statusText}");
                            return;
                        }

                        $record = [
                            'checkpoint_external_id' => $checkpoint,
                            'checkpoint' => $checkpointName,
                            'plate_number' => $plate,
                            'record_date' => $dateTime->format('Y-m-d'),
                            'time_slot_start' => $dateTime->format('H:i'),
                            'time_slot_end' => null,
                            'status' => $status,
                            'scraped_at' => now()->format('Y-m-d H:i:s'),
                        ];


                        $records[] = $record;

                        $totalFound++;

                    } catch (\Throwable $e) {

                        $this->warn(
                            'Row parse qilishda xato: ' . $e->getMessage()
                        );
                    }
                });

                /*
                 * Topilgan recordlarni API'ga yuboramiz.
                 */
                if (!empty($records)) {

                    $this->info(
                        'Topildi: ' . count($records) . ' ta record. API ga yuborilmoqda...'
                    );

                    $written = $this->sendRecordsToApi($records);

                    $totalSent += $written;

                    $this->info(
                        "API written: {$written}"
                    );
                }

                /*
                 * Qoldau serveriga juda tez-tez request yubormaslik.
                 */
                sleep(1);
            }
        }

        $this->newLine();

        $this->info('========================================');
        $this->info('SCRAPING TUGADI');
        $this->info('========================================');
        $this->info("Topilgan recordlar: {$totalFound}");
        $this->info("API written: {$totalSent}");
        $this->info('========================================');

        return self::SUCCESS;
    }

    /**
     * Qoldau'dan kelayotgan sana va vaqtni Carbon'ga o'tkazadi.
     */
    private function parseDateTime(string $date, string $time): ?Carbon
    {
        $value = trim($date . ' ' . $time);

        if ($value === '') {
            return null;
        }

        /*
         * Qoldau'dagi ehtimoliy formatlarni navbat bilan tekshiramiz.
         */
        $formats = [
            'd.m.Y H:i',
            'd.m.Y H:i:s',
            'd/M/Y H:i',
            'd/M/Y H:i:s',
            'd-M-Y H:i',
            'd-M-Y H:i:s',
            'Y-m-d H:i',
            'Y-m-d H:i:s',
            'd/m/Y H:i',
            'd/m/Y H:i:s',
        ];

        foreach ($formats as $format) {

            try {

                $parsed = Carbon::createFromFormat(
                    $format,
                    $value
                );

                if ($parsed !== false) {
                    return $parsed;
                }

            } catch (\Throwable $e) {
                // Keyingi formatni tekshiramiz.
            }
        }

        /*
         * Agar yuqoridagi formatlarning hech biri ishlamasa,
         * Carbon::parse bilan oxirgi marta urinib ko'ramiz.
         */
        try {

            return Carbon::parse($value);

        } catch (\Throwable $e) {

            return null;
        }
    }

    /**
     * Recordlarni API'ga batch qilib yuboradi.
     */
    private function sendRecordsToApi(array $records): int
    {
        try {

            $response = Http::timeout(60)
                ->retry(3, 1000)
                ->acceptJson()
                ->withHeaders([
                    'X-API-KEY' => config('services.checkpoint_scraper.api_key'),
                ])
                ->post(
                    $this->apiUrl,
                    [
                        'records' => $records,
                    ]
                );

        } catch (\Throwable $e) {

            $this->error(
                'API request xatosi: ' . $e->getMessage()
            );

            return 0;
        }

        if (!$response->successful()) {

            $this->error(
                'API HTTP error: ' . $response->status()
            );

            $this->error(
                'Response: ' . $response->body()
            );

            return 0;
        }

        $data = $response->json();

        $this->info(
            'API response: ' . $response->body()
        );

        return (int) ($data['written'] ?? 0);
    }

    /**
     * Qoldau'dagi checkpoint uchun oxirgi page'ni aniqlaydi.
     */
    private function detectLastPage(string $checkpoint): int
    {
        try {

            $response = Http::timeout(60)
                ->retry(3, 1000)
                ->get(
                    'https://cgr.qoldau.kz/ru/registry/scoreboard',
                    [
                        'flCheckpoint' => $checkpoint,
                        'p' => 1,
                    ]
                );

        } catch (\Throwable $e) {

            $this->error(
                "Last page aniqlashda xato: " . $e->getMessage()
            );

            return 1;
        }

        if (!$response->successful()) {
            return 1;
        }

        $crawler = new Crawler($response->body());

        $pages = $crawler->filter('.pagination .page-link');

        $lastPage = 1;

        $pages->each(function (Crawler $node) use (&$lastPage) {

            $text = trim($node->text());

            if (is_numeric($text)) {

                $lastPage = max(
                    $lastPage,
                    (int) $text
                );
            }
        });

        return $lastPage;
    }

    /**
     * Checkpoint ID -> checkpoint nomi.
     */
    private function getCheckpointName(string $checkpoint): string
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
            '314889163298000000' => 'Порт Курык',
        ];

        return $map[$checkpoint] ?? $checkpoint;
    }

    /**
     * Barcha checkpointlar.
     */
    private function getCheckpointGroups(): array
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
            ],
        ];
    }
}