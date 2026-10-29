<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * orginfo.uz'dan qidirilgan korxona nomi va topilgan INN (topilmasa null).
 */
class OrginfoInn extends Model
{
    /** Topilmagan nomlar shuncha kundan keyin qayta qidiriladi (yangi ro'yxatdan o'tgan bo'lishi mumkin) */
    public const RECHECK_MISSING_AFTER_DAYS = 7;

    protected $fillable = ['name', 'inn', 'checked_at'];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime'];
    }

    /** Keshdagi yaroqli yozuv: topilgani doim, topilmagani 7 kun ichida */
    public static function cachedFor(string $name): ?self
    {
        $row = static::where('name', $name)->first();

        if (! $row || ($row->inn === null && $row->checked_at->lt(now()->subDays(self::RECHECK_MISSING_AFTER_DAYS)))) {
            return null;
        }

        return $row;
    }
}
