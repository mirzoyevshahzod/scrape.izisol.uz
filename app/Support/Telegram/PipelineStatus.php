<?php

namespace App\Support\Telegram;

use Illuminate\Support\Carbon;

/**
 * Kunlik pipeline (scraping -> warehouse -> CRM import -> Telegram'ga
 * fayl yuborish) bosqichlaridan qaysi biri muvaffaqiyatsiz bo'lganini
 * storage/app/pipeline-status/{sana}.json faylida kuzatib boradi.
 * `routes/console.php`dagi har bir bosqich ->onFailure() orqali shu yerga
 * yozadi, `telegram:daily-status` buyrug'i esa shu faylni o'qib, kunlik
 * hisobotni yuboradi.
 */
class PipelineStatus
{
    public static function recordFailure(string $step, string $message): void
    {
        $failures = self::todayFailures();
        $failures[$step] = $message;

        file_put_contents(self::path(), json_encode($failures, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string,string>
     */
    public static function todayFailures(): array
    {
        $path = self::path();

        if (! file_exists($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true) ?: [];
    }

    private static function path(): string
    {
        $dir = storage_path('app/pipeline-status');

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir . '/' . Carbon::now()->format('Y-m-d') . '.json';
    }
}
