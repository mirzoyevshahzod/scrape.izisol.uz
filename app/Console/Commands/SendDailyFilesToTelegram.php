<?php

namespace App\Console\Commands;

use App\Support\Telegram\PipelineStatus;
use App\Support\Telegram\TelegramSender;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Har kungi scraping/warehouse pipeline natijasida hosil bo'lgan fayllarni
 * Telegram orqali belgilangan chat'larga avtomatik yuboradi (avval
 * SubscriberController orqali qo'lda yuborilgan jarayonning avtomatlashgani).
 *
 * Har bir toifa uchun eng oxirgi faylni topadi va uning sanasi BUGUNGI
 * kun bilan mos kelishini tekshiradi — aks holda (fayl topilmasa yoki
 * eski bo'lsa) shu toifani o'tkazib yuboradi va buni PipelineStatus'ga
 * xato sifatida yozadi, shunda `telegram:daily-status` kunlik hisobotda
 * buni ko'rsatadi.
 */
class SendDailyFilesToTelegram extends Command
{
    protected $signature = 'telegram:send-daily-files';

    protected $description = 'Kunlik scrape/warehouse fayllarini Telegram orqali belgilangan chatlarga yuboradi';

    /** @var array<int,array{label:string,dir:string,pattern:string,chats:array<int,string>}> */
    private array $items;

    public function __construct()
    {
        parent::__construct();

        $mainChats = ['75714317', '7510409703'];
        $finalChats = ['7510409703', '6757738816'];

        $this->items = [
            ['label' => 'Zitic', 'dir' => storage_path('app/zitic'), 'pattern' => 'zitic_*.xlsx', 'chats' => $mainChats],
            ['label' => 'Turkey', 'dir' => storage_path('app/turkey'), 'pattern' => 'turkey_scrape-*.xlsx', 'chats' => $mainChats],
            ['label' => 'Declarant (Benyakoni)', 'dir' => storage_path('app/declarant'), 'pattern' => 'benyakoni-*.xlsx', 'chats' => $mainChats],
            ['label' => 'Declarant (Kamennii Log)', 'dir' => storage_path('app/declarant'), 'pattern' => 'kamennii-log-*.xlsx', 'chats' => $mainChats],
            ['label' => 'Kazakhstan-China', 'dir' => storage_path('app/qozoq'), 'pattern' => 'kazakhstan-china-*.xlsx', 'chats' => $mainChats],
            ['label' => 'Qozoq tekshirilgan', 'dir' => storage_path('app/import_qozoq'), 'pattern' => 'qozoq_*_tekshirilgan.xlsx', 'chats' => $mainChats],
            ['label' => 'Yakuniy (merge + operator + INN)', 'dir' => storage_path('app/orginfo'), 'pattern' => 'malumotlar_*.xlsx', 'chats' => $finalChats],
        ];
    }

    public function handle(): int
    {
        $sender = new TelegramSender();
        $today = Carbon::now()->format('Y-m-d');
        $hasFailure = false;

        foreach ($this->items as $item) {
            $file = $this->latestFile($item['dir'], $item['pattern']);

            if (! $file) {
                $this->warn("{$item['label']}: fayl topilmadi ({$item['dir']}/{$item['pattern']}), o'tkazib yuborildi.");
                PipelineStatus::recordFailure("telegram:send-daily-files [{$item['label']}]", 'Fayl umuman topilmadi.');
                $hasFailure = true;

                continue;
            }

            if (Carbon::createFromTimestamp(filemtime($file))->format('Y-m-d') !== $today) {
                $this->warn("{$item['label']}: eng oxirgi fayl bugungi emas ({$file}), o'tkazib yuborildi.");
                PipelineStatus::recordFailure("telegram:send-daily-files [{$item['label']}]", "Bugungi fayl topilmadi, eng oxirgisi: {$file}");
                $hasFailure = true;

                continue;
            }

            foreach ($item['chats'] as $chatId) {
                $sent = $sender->sendDocument($chatId, $file, $item['label'] . ' — ' . $today);

                if (! $sent) {
                    $this->error("{$item['label']}: {$chatId}'ga yuborib bo'lmadi.");
                    PipelineStatus::recordFailure("telegram:send-daily-files [{$item['label']}]", "Telegram'ga ({$chatId}) yuborib bo'lmadi.");
                    $hasFailure = true;
                } else {
                    $this->info("{$item['label']}: {$chatId}'ga yuborildi ({$file}).");
                }
            }
        }

        return $hasFailure ? self::FAILURE : self::SUCCESS;
    }

    private function latestFile(string $directory, string $pattern): ?string
    {
        $files = glob($directory . '/' . $pattern);

        if (empty($files)) {
            return null;
        }

        usort($files, fn ($a, $b) => filemtime($b) - filemtime($a));

        return $files[0];
    }
}
