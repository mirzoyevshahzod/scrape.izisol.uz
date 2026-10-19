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

    /** Bazada saqlanadigan natijaning maksimal hajmi (oxirgi qismi qoldiriladi) */
    public const MAX_OUTPUT_BYTES = 200_000;

    protected $fillable = ['operation', 'status', 'user_id', 'output', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return [
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

    public function appendOutput(string $text): void
    {
        $output = ($this->output ?? '') . $text;

        if (strlen($output) > self::MAX_OUTPUT_BYTES) {
            $output = "…(boshi qisqartirildi)…\n" . substr($output, -self::MAX_OUTPUT_BYTES);
        }

        $this->output = $output;
    }
}
