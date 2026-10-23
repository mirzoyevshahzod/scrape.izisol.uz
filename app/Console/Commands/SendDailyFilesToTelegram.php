<?php

namespace App\Console\Commands;

use App\Support\DailyFiles;
use App\Support\Telegram\PipelineStatus;
use App\Support\Telegram\SentFilesTracker;
use App\Support\Telegram\TelegramSender;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

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
 *
 * `--date=YYYY-MM-DD` berilsa, bugungi emas, o'sha kunning fayllari
 * (nomida shu sana bor eng oxirgisi) yuboriladi.
 *
 * `enrich` belgilangan fayllar yuborishdan oldin `crm:plate-operators`
 * orqali "Manager" ustuni bilan boyitilib, kompaniya bo'yicha saralanadi.
 * Asl fayllarga tegilmaydi (ularni warehouse import va crm:daily-import
 * ham o'qiydi) — boyitilgan nusxa storage/app/telegram-enriched/ ga
 * yoziladi va o'sha yuboriladi. Boyitib bo'lmasa, asl fayl yuboriladi.
 */
class SendDailyFilesToTelegram extends Command
{
    protected $signature = 'telegram:send-daily-files
                            {--date= : Shu kun (YYYY-MM-DD) fayllarini yuborish. Bo\'sh bo\'lsa — bugungi fayllar}';

    protected $description = 'Kunlik scrape/warehouse fayllarini Telegram orqali belgilangan chatlarga yuboradi';

    /** @var array<int,array{label:string,dir:string,pattern:string,chats:array<int,string>,enrich?:bool}> */
    private array $items;

    public function __construct()
    {
        parent::__construct();

        $mainChats = ['75714317', '7510409703', '8776207278'];
        $finalChats = ['7510409703', '6757738816'];

        $this->items = [
            ['label' => 'Zitic', 'dir' => storage_path('app/zitic'), 'pattern' => 'zitic_*.xlsx', 'chats' => $mainChats, 'enrich' => true],
            ['label' => 'Turkey', 'dir' => storage_path('app/turkey'), 'pattern' => 'turkey_scrape-*.xlsx', 'chats' => $mainChats, 'enrich' => true],
            ['label' => 'Declarant (Benyakoni)', 'dir' => storage_path('app/declarant'), 'pattern' => 'benyakoni-*.xlsx', 'chats' => $mainChats, 'enrich' => true],
            ['label' => 'Declarant (Kamennii Log)', 'dir' => storage_path('app/declarant'), 'pattern' => 'kamennii-log-*.xlsx', 'chats' => $mainChats, 'enrich' => true],
            ['label' => 'Kazakhstan-China', 'dir' => storage_path('app/qozoq'), 'pattern' => 'kazakhstan-china-*.xlsx', 'chats' => $mainChats, 'enrich' => true],
            ['label' => 'Qozoq tekshirilgan', 'dir' => storage_path('app/import_qozoq'), 'pattern' => 'qozoq_*_tekshirilgan.xlsx', 'chats' => $mainChats, 'enrich' => true],
            ['label' => 'Yakuniy (merge + operator + INN)', 'dir' => storage_path('app/orginfo'), 'pattern' => 'malumotlar_*.xlsx', 'chats' => $finalChats],
        ];
    }

    public function handle(): int
    {
        try {
            $date = DailyFiles::parseDateOption($this->option('date'));
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $sender = new TelegramSender();
        $today = $date ?? Carbon::now()->format('Y-m-d');
        $hasFailure = false;

        if ($date) {
            $this->info("Sana: {$date}");
        }

        foreach ($this->items as $item) {
            $file = DailyFiles::latest($item['dir'], $item['pattern'], $date);

            if (! $file) {
                $this->warn("{$item['label']}: fayl topilmadi ({$item['dir']}/{$item['pattern']}" . ($date ? ", {$date}" : '') . "), o'tkazib yuborildi.");
                PipelineStatus::recordFailure("telegram:send-daily-files [{$item['label']}]", $date ? "{$date} uchun fayl topilmadi." : 'Fayl umuman topilmadi.');
                $hasFailure = true;

                continue;
            }

            // --date berilmagan (kundalik jadval) holatda eng oxirgi fayl bugungi bo'lishi shart
            if (! $date && Carbon::createFromTimestamp(filemtime($file))->format('Y-m-d') !== $today) {
                $this->warn("{$item['label']}: eng oxirgi fayl bugungi emas ({$file}), o'tkazib yuborildi.");
                PipelineStatus::recordFailure("telegram:send-daily-files [{$item['label']}]", "Bugungi fayl topilmadi, eng oxirgisi: {$file}");
                $hasFailure = true;

                continue;
            }

            if (! empty($item['enrich'])) {
                $file = $this->enrichedCopy($file, $item['label']);
            }

            foreach ($item['chats'] as $chatId) {
                if (SentFilesTracker::alreadySent($chatId, $file)) {
                    $this->line("{$item['label']}: {$chatId}'ga bugun allaqachon yuborilgan, o'tkazib yuborildi.");

                    continue;
                }

                $sent = $sender->sendDocument($chatId, $file, $item['label'] . ' — ' . $today);

                if (! $sent) {
                    $this->error("{$item['label']}: {$chatId}'ga yuborib bo'lmadi.");
                    PipelineStatus::recordFailure("telegram:send-daily-files [{$item['label']}]", "Telegram'ga ({$chatId}) yuborib bo'lmadi.");
                    $hasFailure = true;
                } else {
                    SentFilesTracker::markSent($chatId, $file);
                    $this->info("{$item['label']}: {$chatId}'ga yuborildi ({$file}).");
                }
            }
        }

        return $hasFailure ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Faylning "Manager" ustunli, saralangan nusxasini qaytaradi. Nusxa
     * allaqachon bor va asl fayldan yangi bo'lsa, qayta yasalmaydi (buyruq
     * qayta ishga tushirilganda CRM'ga qayta so'rov yubormaslik uchun).
     * Xato bo'lsa asl faylni qaytaradi.
     */
    private function enrichedCopy(string $file, string $label): string
    {
        $dir = storage_path('app/telegram-enriched');

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $copy = $dir . '/' . basename($file);

        if (file_exists($copy) && filemtime($copy) >= filemtime($file)) {
            return $copy;
        }

        $this->info("{$label}: Manager ustuni qo'shilmoqda...");

        try {
            $code = Artisan::call('crm:plate-operators', ['file' => $file, '--output' => $copy]);
        } catch (\Throwable $e) {
            $code = self::FAILURE;
            Artisan::output();
        }

        if ($code === self::SUCCESS && file_exists($copy)) {
            return $copy;
        }

        @unlink($copy);
        $this->warn("{$label}: Manager qo'shib bo'lmadi, asl fayl yuboriladi.");
        PipelineStatus::recordFailure("telegram:send-daily-files [{$label}]", 'crm:plate-operators muvaffaqiyatsiz — Manager ustunisiz yuborildi.');

        return $file;
    }
}
