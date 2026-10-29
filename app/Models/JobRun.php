<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Boshqaruv" sahifasidan ishga tushirilgan bitta amal (RunOperationJob).
 */
class JobRun extends Model
{
    public const QUEUED = 'queued';
    public const RUNNING = 'running';
    public const SUCCESS = 'success';
    public const FAILED = 'failed';

    /**
     * Shuncha vaqtdan beri "navbatda" yoki "ishlayapti" holatida qolgan
     * ish tiqilib qolgan hisoblanadi (worker o'chib qolgan va h.k.) —
     * u yangi ishni ishga tushirishga to'sqinlik qilmaydi.
     */
    public const STALE_AFTER_MINUTES = 120;

    /** Bazada saqlanadigan natijaning maksimal hajmi (oxirgi qismi qoldiriladi) — ~25 ming log qatori */
    public const MAX_OUTPUT_BYTES = 1_000_000;

    private const TRUNCATED_MARK = "…(boshi qisqartirildi)…\n";

    protected $fillable = ['operation', 'status', 'user_id', 'params', 'output', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return [
            'params' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Hozir navbatda turgan yoki ishlayotgan (tiqilib qolmagan) ishlar */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::QUEUED, self::RUNNING])
            ->where('updated_at', '>=', now()->subMinutes(self::STALE_AFTER_MINUTES));
    }

    /**
     * Natijaga matn qo'shadi; hajm oshsa boshini qisqartiradi.
     *
     * Kesish bayt bo'yicha emas, belgi va qator chegarasidan — aks holda
     * ko'p baytli belgi ("—" = E2 80 94) o'rtasidan bo'linib, MySQL buzilgan
     * UTF-8'ni qabul qilmaydi ("Incorrect string value: '\x94...'").
     */
    public function appendOutput(string $text): void
    {
        $output = ($this->output ?? '') . mb_scrub($text, 'UTF-8');

        if (strlen($output) > self::MAX_OUTPUT_BYTES) {
            // oldingi qisqartirish belgisi takrorlanmasin
            if (str_starts_with($output, self::TRUNCATED_MARK)) {
                $output = substr($output, strlen(self::TRUNCATED_MARK));
            }

            $output = self::TRUNCATED_MARK . self::tail($output, self::MAX_OUTPUT_BYTES);
        }

        $this->output = $output;
    }

    /** Natijaning oxirgi qismi (sahifaga yuborish uchun) — belgi va qator chegarasidan */
    public function outputTail(int $bytes): string
    {
        $output = $this->output ?? '';

        return strlen($output) > $bytes ? self::TRUNCATED_MARK . self::tail($output, $bytes) : $output;
    }

    private static function tail(string $text, int $bytes): string
    {
        // mb_strcut boshlanish nuqtasini belgi boshiga suradi; keyin chala qatorni tashlaymiz
        $tail = mb_strcut($text, max(0, strlen($text) - $bytes), null, 'UTF-8');
        $newline = strpos($tail, "\n");

        return $newline === false ? $tail : substr($tail, $newline + 1);
    }
}
