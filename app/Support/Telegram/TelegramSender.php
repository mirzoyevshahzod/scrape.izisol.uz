<?php

namespace App\Support\Telegram;

use Illuminate\Support\Facades\Http;

/**
 * Kunlik pipeline fayllarini/xabarlarini Telegram orqali yuborish uchun
 * yordamchi. `TELEGRAM_BOT_TOKEN1` (config('services.telegram.daily_files_bot_token'))
 * botidan foydalanadi — bu webhookda ishlatilayotgan asosiy bot
 * (`TELEGRAM_BOT_TOKEN`) bilan bir xil emas.
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
        return Http::timeout(30)->post($this->apiUrl . 'sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
        ])->successful();
    }

    public function sendDocument(string $chatId, string $filePath, ?string $caption = null): bool
    {
        return Http::timeout(60)
            ->attach('document', file_get_contents($filePath), basename($filePath))
            ->post($this->apiUrl . 'sendDocument', array_filter([
                'chat_id' => $chatId,
                'caption' => $caption,
            ]))
            ->successful();
    }
}
