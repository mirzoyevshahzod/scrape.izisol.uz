<?php

namespace App\Console\Commands;

use App\Support\Telegram\PipelineStatus;
use App\Support\Telegram\TelegramSender;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Kun davomidagi barcha pipeline bosqichlaridan (scraping, warehouse,
 * CRM import, Telegram'ga yuborish) hech bo'lmasa bittasi muvaffaqiyatsiz
 * bo'lgan bo'lsa (routes/console.php'dagi ->onFailure() qo'ng'iroqlari
 * orqali PipelineStatus'ga yozib qo'yiladi), shular haqida bitta yig'ma
 * xabar yuboradi. Aks holda "muvaffaqiyatli" xabari yuboriladi.
 */
class SendDailyStatusToTelegram extends Command
{
    protected $signature = 'telegram:daily-status';

    protected $description = "Kunlik pipeline holati haqida 7510409703'ga hisobot yuboradi";

    private const REPORT_CHAT_ID = '7510409703';

    public function handle(): int
    {
        $today = Carbon::now()->format('Y-m-d');
        $failures = PipelineStatus::todayFailures();
        $sender = new TelegramSender();

        if (empty($failures)) {
            $sender->sendMessage(
                self::REPORT_CHAT_ID,
                "✅ {$today}: bugungi pipeline (scraping, warehouse, CRM import, fayllarni yuborish) muvaffaqiyatli ishladi."
            );
            $this->info('Muvaffaqiyatli xabar yuborildi.');

            return self::SUCCESS;
        }

        $lines = ["❌ {$today}: bugungi pipeline'da xatoliklar bor:"];

        foreach ($failures as $step => $message) {
            $lines[] = "— {$step}: {$message}";
        }

        $sender->sendMessage(self::REPORT_CHAT_ID, implode("\n", $lines));
        $this->info('Xatolik xabari yuborildi.');

        return self::SUCCESS;
    }
}
