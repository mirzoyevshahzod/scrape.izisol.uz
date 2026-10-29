<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Mintrans'da ma'lumoti chiqmagan avtoraqam (yoki tekshirishda xato bo'lgan).
 */
class MintransNotFound extends Model
{
    public const NOT_FOUND = 'not_found';
    public const ERROR = 'error';

    protected $table = 'mintrans_not_found';

    protected $fillable = ['plate', 'status', 'message', 'job_run_id', 'checks', 'checked_at'];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime'];
    }

    /** Tekshiruv natijasini yozadi: yangi raqam bo'lsa yaratadi, bo'lsa holatini yangilab hisoblagichni oshiradi */
    public static function record(string $plate, string $status, ?string $message = null, ?int $jobRunId = null): void
    {
        $row = static::firstOrNew(['plate' => $plate]);
        $row->fill([
            'status' => $status,
            'message' => $message ? mb_substr($message, 0, 250) : null,
            'job_run_id' => $jobRunId,
            'checked_at' => now(),
            'checks' => $row->exists ? $row->checks + 1 : 1,
        ])->save();
    }
}
