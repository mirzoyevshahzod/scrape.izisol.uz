<?php

namespace App\Support\Telegram;

use Illuminate\Support\Facades\Http;

/**
 * Kunlik pipeline fayllarini/xabarlarini Telegram orqali yuborish uchun
 * yordamchi. `TELEGRAM_SCRAPING_BOT_TOKEN` (config('services.telegram.daily_files_bot_token'))
 * botidan foydalanadi — bu webhookda ishlatilayotgan asosiy bot
 * (`TELEGRAM_BOT_TOKEN`) bilan bir xil emas.
 *
 * Serverdan `api.telegram.org`ga ulanish ba'zan (taxminan har 2-3
 * urinishdan bittasi) vaqtinchalik uzilib qoladi (Rossiya provayderlarida
 * Telegram'ga nisbatan qo'llaniladigan DPI cheklovlari tufayli), shuning
 * uchun har bir so'rov bir necha marta qayta uriniladi — ulanish darajasi
 * ~60% bo'lsa ham, 5 urinishdan hech bo'lmasa bittasi o'tish ehtimoli
 * juda yuqori.
 */
class TelegramSender
{
    private string $apiUrl;

    public function __construct(?string $botToken = null)
    {
        $botToken ??= config('services.telegram.daily_files_bot_token');

        $this->apiUrl = 'https://api.telegram.org/bot' . $botToken . '/';
    }

    public function sendMessage(string $chatId, string $text): bool
    {
        try {
            return Http::connectTimeout(8)
                ->timeout(30)
                ->retry(5, 3000)
                ->post($this->apiUrl . 'sendMessage', [
                    'chat_id' => $chatId,
                    'text' => $text,
                ])
                ->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function sendDocument(string $chatId, string $filePath, ?string $caption = null): bool
    {
        try {
            return Http::connectTimeout(8)
                ->timeout(60)
                ->retry(5, 3000)
                ->attach('document', file_get_contents($filePath), basename($filePath))
                ->post($this->apiUrl . 'sendDocument', array_filter([
                    'chat_id' => $chatId,
                    'caption' => $caption,
                ]))
                ->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
