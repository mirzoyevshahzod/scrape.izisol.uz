<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * spot:events kalit so'zi: sarlavhasida shu so'z bor tadbir Telegram'ga
 * yuboriladi. Admin panelda ("Spot.uz tadbirlari" sahifasi) boshqariladi.
 */
class SpotEventKeyword extends Model
{
    protected $fillable = ['keyword'];

    /** @return list<string> */
    public static function list(): array
    {
        return static::orderBy('id')->pluck('keyword')->all();
    }
}
