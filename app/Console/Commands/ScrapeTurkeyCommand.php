<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use Carbon\Carbon;

class ScrapeTurkeyCommand extends Command
{
    protected $signature = 'scrape:turkey-command';

    protected $description =
        'Scrape hopatirparki.com and send records to API';

    private string $apiUrl =
        'https://b670-93-188-81-10.ngrok-free.app/api/checkpoints/queue-records';

    public function handle()
    {
        $this->info('HTML scraping started...');

        /*
         * GET HTML
         */
        try {

            $response = Http::timeout(60)
                ->retry(3, 1000)
                ->get(
                    'https://www.hopatirparki.com/tirparki/arhavilimansiragumruklu.asp'
                );

        } catch (\Throwable $e) {

            $this->error(
                'Saytga ulanishda xato: ' .
                $e->getMessage()
            );

            return Command::FAILURE;
        }

        if (! $response->successful()) {

            $this->error(
                'Sayt ochilmadi. HTTP: ' .
                $response->status()
            );

            return Command::FAILURE;
        }

        /*
         * PARSE HTML
         */
        $crawler = new Crawler(
            $response->body()
        );

        $rows = $crawler->filter(
            '#myTable tbody tr'
        );

        $this->info(
            'Topilgan rowlar: ' .
            $rows->count()
        );

        if ($rows->count() === 0) {

            $this->error(
                'No rows found!'
            );

            return Command::FAILURE;
        }

        /*
         * API RECORDS
         */
        $records = [];

        foreach ($rows as $tr) {

            try {

                $td = new Crawler($tr);

                $td = $td->filter('td');

                if ($td->count() < 5) {
                    continue;
                }

                /*
                 * PLATE
                 *
                 * Eski command'dagi kabi
                 */
                $plakaRaw = trim(
                    $td->eq(2)->text()
                );

                $plaka = strtoupper(
                    preg_replace(
                        '/\s+\(*.*\)*/',
                        '',
                        explode(
                            "\n",
                            $plakaRaw
                        )[0]
                    )
                );

                if (! $plaka) {
                    continue;
                }

                /*
                 * DATA
                 */
                $tarih = trim(
                    $td->eq(3)->text()
                );

                $yer = trim(
                    $td->eq(4)->text()
                );

                /*
                 * DATE
                 */
                try {

                    $dateTime = Carbon::parse(
                        $tarih
                    );

                } catch (\Throwable $e) {

                    $this->warn(
                        "Sana parse bo'lmadi: {$tarih} | {$plaka}"
                    );

                    continue;
                }

                /*
                 * API RECORD
                 */
                $records[] = [

                    'checkpoint_external_id' =>
                        'hopatirparki',

                    'checkpoint' =>
                        'Хопа-Сарпи',

                    'plate_number' =>
                        $plaka,

                    'record_date' =>
                        $dateTime->format(
                            'Y-m-d'
                        ),

                    'time_slot_start' =>
                        $dateTime->format(
                            'H:i'
                        ),

                    'time_slot_end' =>
                        null,

                    'status' =>
                        'in_queue',

                    'scraped_at' =>
                        now()->format(
                            'Y-m-d H:i:s'
                        ),
                ];

                $this->info(
                    "Topildi: {$plaka} | {$yer} -> in_queue"
                );

            } catch (\Throwable $e) {

                \Log::error(
                    'Row parsing error: ' .
                    $e->getMessage()
                );

                $this->warn(
                    'Row xatosi: ' .
                    $e->getMessage()
                );
            }
        }

        /*
         * SEND TO API
         */
        if (empty($records)) {

            $this->warn(
                'API ga yuboriladigan record topilmadi.'
            );

            return Command::SUCCESS;
        }

        $this->info(
            'API ga yuborilmoqda: ' .
            count($records) .
            ' ta record'
        );

        $written = $this->sendRecordsToApi(
            $records
        );

        $this->info(
            "API written: {$written}"
        );

        $this->info(
            'DONE ✅'
        );

        return Command::SUCCESS;
    }

    /*
     * SEND RECORDS TO API
     */
    private function sendRecordsToApi(
        array $records
    ): int {

        try {

            $response = Http::timeout(60)
                ->retry(3, 1000)
                ->acceptJson()
                ->withHeaders([
                    'X-API-KEY' =>
                        config(
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

        if (! $response->successful()) {

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