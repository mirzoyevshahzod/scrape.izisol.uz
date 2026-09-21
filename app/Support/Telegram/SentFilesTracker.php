<?php

namespace App\Support\Telegram;

use Illuminate\Support\Carbon;

/**
 * `telegram:send-daily-files` bir necha marta ishga tushirilsa ham
 * (qo'lda qayta sinash, yoki scheduler tasodifan ikki marta chaqirsa),
 * bir xil faylni bir xil chat'ga ikki marta yubormasligi uchun
 * storage/app/telegram-sent/{sana}.json faylida (chat_id + fayl nomi)
 * juftliklarini kuzatib boradi.
 */
class SentFilesTracker
{
    public static function alreadySent(string $chatId, string $filePath): bool
    {
        return isset(self::todaySent()[self::key($chatId, $filePath)]);
    }

    public static function markSent(string $chatId, string $filePath): void
    {
        $sent = self::todaySent();
        $sent[self::key($chatId, $filePath)] = true;

        file_put_contents(self::path(), json_encode($sent, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private static function key(string $chatId, string $filePath): string
    {
        return $chatId . ':' . basename($filePath);
    }

    /**
     * @return array<string,bool>
     */
    private static function todaySent(): array
    {
        $path = self::path();

        if (! file_exists($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true) ?: [];
    }

    private static function path(): string
    {
        $dir = storage_path('app/telegram-sent');

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir . '/' . Carbon::now()->format('Y-m-d') . '.json';
    }
}
