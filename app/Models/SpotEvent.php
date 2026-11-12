<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * spot.uz/ru/events sahifasidagi tadbir (spot:events).
 */
class SpotEvent extends Model
{
    protected $fillable = [
        'url', 'title', 'category', 'city', 'price', 'date_text', 'starts_on', 'ends_on',
        'start_time', 'description', 'registration_url', 'matched_keyword', 'notified_at', 'reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'notified_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }
}
