<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Auto;

class ScrapeBelarusCommand extends Command
{
    protected $signature = 'scrape:belarus-queue';

    protected $description =
        'Scrape Belarus queue for both checkpoints and send Uzbek cars to API';

    private string $apiUrl =
        'https://358b-93-188-81-10.ngrok-free.app/api/checkpoints/queue-records';

    private array $zones = [
        [
            'checkpoint_id' => '53d94097-2b34-11ec-8467-ac1f6bf889c0',
            'name' => 'Бенякони - Шальчининкай',
        ],
        [
            'checkpoint_id' => 'b60677d4-8a00-4f93-a781-e129e1692a03',
            'name' => 'Каменный Лог - Мядининкай',
        ],
    ];

    private array $uzbekPatterns = [
        '/^(01|10|20|25|30|40|50|60|70|75|80|85|90|95)[A-Z]\d{3}[A-Z]{2}$/',
        '/^(01|10|20|25|30|40|50|60|70|75|80|85|90|95)\d{3}[A-Z]{3}$/',
        '/^(01|10|20|25|30|40|50|60|70|75|80|85|90|95)\d{4}[A-Z]{2}$/',
    ];

    public function handle()
    {
        set_time_limit(0);

        $this->info('Belarus scraping boshlandi...');
        $this->info('Jami chegara: ' . count($this->zones));

        $totalFound = 0;
        $totalSent = 0;

        foreach ($this->zones as $zone) {

            $checkpointId = $zone['checkpoint_id'];
            $checkpointName = $zone['name'];

            $this->newLine();

            $this->info('========================================');
            $this->info("Chegara: {$checkpointName}");
            $this->info("Checkpoint ID: {$checkpointId}");
            $this->info('========================================');

            try {
                $response = Http::timeout(60)
                    ->retry(3, 1000)
                    ->acceptJson()
                    ->get(
                        'https://belarusborder.by/info/monitoring-new',
                        [
                            'token' => 'test',
                            'checkpointId' => $checkpointId,
                        ]
                    );

            } catch (\Throwable $e) {

                $this->error(
                    'API request xatosi: ' . $e->getMessage()
                );

                continue;
            }

            if (!$response->successful()) {

                $this->error(
                    'Belarus API HTTP error: ' . $response->status()
                );

                $this->error(
                    'Response: ' . $response->body()
                );

                continue;
            }

            $data = $response->json();

            $records = [];

            foreach ($data['truckLiveQueue'] ?? [] as $item) {

                $regnum = strtoupper(
                    preg_replace(
                        '/[^A-Z0-9]/',
                        '',
                        $item['regnum'] ?? ''
                    )
                );

                if ($regnum === '') {
                    continue;
                }

                $this->info("Processing: {$regnum}");


                /*
                 * Faqat kerakli statuslar.
                 */
                $statusCode = (int) ($item['status'] ?? 0);

                $statusMap = [
                    2 => 'passed', // Прибыл в ЗО
                    3 => 'passed', // Вызван в ПП
                ];

                $status = $statusMap[$statusCode] ?? null;

                if ($status === null) {
                    $statusName = match ($statusCode) {
                        1 => 'В очереди',
                        default => 'Noma\'lum',
                    };

                    $this->info(
                        "O'tkazib yuborildi: {$regnum} | status: {$statusName}"
                    );

                    continue;
                }

                $statusName = match ($statusCode) {
                    2 => 'Прибыл в ЗО',
                    3 => 'Вызван в ПП',
                    default => 'Noma\'lum',
                };

                $this->info(
                    "Topildi: {$regnum} | {$statusName} -> {$status}"
                );

                /*
                 * Auto ma'lumotlarini olish.
                 */
                $auto = Auto::where(
                    'state_number',
                    $regnum
                )->first();

                /*
                 * Registration date.
                 */
                $registrationDate =
                    $item['registration_date'] ?? null;

                if (!$registrationDate) {
                    $this->warn(
                        "Registration date yo'q: {$regnum}"
                    );

                    continue;
                }

                /*
                 * API uchun sana va vaqtni ajratamiz.
                 */
                try {
                    $dateTime = \Carbon\Carbon::parse(
                        $registrationDate
                    );
                } catch (\Throwable $e) {

                    $this->warn(
                        "Sana parse bo'lmadi: {$registrationDate}"
                    );

                    continue;
                }

                $record = [
                    'checkpoint_external_id' => $checkpointId,
                    'checkpoint' => $checkpointName,

                    'plate_number' => $regnum,

                    'record_date' => $dateTime->format('Y-m-d'),

                    'time_slot_start' => $dateTime->format('H:i'),

                    'time_slot_end' => null,

                    'status' => $status,

                    'scraped_at' => now()->format(
                        'Y-m-d H:i:s'
                    ),
                ];

                $records[] = $record;

                $totalFound++;
            }

            /*
             * API ga yuborish.
             */
            if (!empty($records)) {

                $this->info(
                    'API ga yuborilmoqda: ' .
                    count($records) .
                    ' ta record'
                );

                $written = $this->sendRecordsToApi(
                    $records
                );

                $totalSent += $written;

                $this->info(
                    "API written: {$written}"
                );

            } else {

                $this->info(
                    'Bu chegarada yuboriladigan record topilmadi.'
                );
            }
        }

        $this->newLine();

        $this->info('========================================');
        $this->info('SCRAPING TUGADI');
        $this->info('========================================');
        $this->info(
            "Topilgan recordlar: {$totalFound}"
        );
        $this->info(
            "API written: {$totalSent}"
        );
        $this->info('========================================');

        return self::SUCCESS;
    }

    /**
     * Recordlarni API'ga yuboradi.
     */
    private function sendRecordsToApi(array $records): int
    {
        try {

            $response = Http::timeout(60)
                ->retry(3, 1000)
                ->acceptJson()
                ->withHeaders([
                    'X-API-KEY' => config(
                        'services.checkpoint_scraper.api_key'
                    ),
                ])
                ->post(
                    $this->apiUrl,
                    [
                        'records' => $records,
                    ]
                );

        } catch (\Throwable $e) {

            $this->error(
                'API request xatosi: ' .
                $e->getMessage()
            );

            return 0;
        }

        if (!$response->successful()) {

            $this->error(
                'API HTTP error: ' .
                $response->status()
            );

            $this->error(
                'Response: ' .
                $response->body()
            );

            return 0;
        }

        $data = $response->json();

        $this->info(
            'API response: ' .
            $response->body()
        );

        return (int) (
            $data['written'] ?? 0
        );
    }


}