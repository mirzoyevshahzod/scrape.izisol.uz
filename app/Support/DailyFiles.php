<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Kunlik scrape/pipeline fayllarini topish.
 *
 * Barcha fayl nomlarida yaratilgan sana bor (zitic_2026-10-19_06-07-57.xlsx,
 * benyakoni-2026-10-19-06-07-55.xlsx, malumotlar_2026-10-19.xlsx ...), shuning
 * uchun "shu kunning fayli" — nomida shu sana bor fayllar ichidan eng
 * oxirgi o'zgartirilgani.
 */
class DailyFiles
{
    /**
     * $date berilmasa — sanasidan qat'i nazar eng oxirgi fayl (avvalgi
     * xatti-harakat); berilsa — nomida shu sana bor eng oxirgi fayl.
     */
    public static function latest(string $directory, string $pattern, ?string $date = null): ?string
    {
        $files = glob($directory . '/' . $pattern) ?: [];

        if ($date !== null) {
            $files = array_filter($files, fn ($file) => str_contains(basename($file), $date));
        }

        if (! $files) {
            return null;
        }

        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return $files[0];
    }

    /**
     * Buyruqlarning --date= qiymatini tekshiradi: bo'sh bo'lsa null,
     * aks holda Y-m-d formatidagi va kelajakda bo'lmagan sana.
     */
    public static function parseDateOption(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            $date = null;
        }

        if (! $date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("--date noto'g'ri: \"{$value}\" (kutilgan format: YYYY-MM-DD).");
        }

        if ($date->isAfter(today())) {
            throw new InvalidArgumentException("--date kelajakdagi sana bo'lishi mumkin emas: {$value}.");
        }

        return $value;
    }
}
