<?php

namespace App\Console\Commands;

use App\Services\DeclarantQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * belarusborder.by API'sidan to'g'ridan-to'g'ri olish. Sayt Cloudflare
 * Turnstile qo'ygandan beri captcha'siz so'rovga bo'sh navbat qaytaradi —
 * bunday javobdan fayl yozilmaydi va buyruq xato bilan tugaydi. Asosiy yo'l
 * endi brauzer orqali: belarus:queue-sync (deploy/belarus-queue-browser).
 */
class ScrapeDeclarantData extends Command
{
    protected $signature = 'scrape:declarant-data {zone}';

    protected $description = 'Scrape Belarus queue and export Uzbek cars';

    public function handle(DeclarantQueue $queue): int
    {
        $zone = $this->argument('zone');

        if (! isset(DeclarantQueue::ZONES[$zone])) {
            $this->error('Invalid zone');

            return self::FAILURE;
        }

        $response = Http::acceptJson()->get('https://belarusborder.by/info/monitoring-new', [
            'token' => 'test',
            'checkpointId' => DeclarantQueue::ZONES[$zone]['checkpoint_id'],
        ]);

        if (! $response->successful()) {
            $this->error('API request failed');

            return self::FAILURE;
        }

        $data = $response->json();

        if (! is_array($data) || ! DeclarantQueue::hasData($data)) {
            $this->error("belarusborder.by bo'sh navbat qaytardi — sayt captcha talab qilyapti. "
                . "Ma'lumot brauzer orqali keladi (belarus:queue-sync), fayl yozilmadi.");

            return self::FAILURE;
        }

        $result = $queue->export($zone, $data);

        $this->info("Navbatda: {$result['queue']} ta, o'zbek raqamli: {$result['rows']} ta");
        $this->info("Excel saved: {$result['path']}");

        return self::SUCCESS;
    }
}
