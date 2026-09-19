<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmToken extends Model
{
    protected $table = 'crm_tokens';

    protected $fillable = [
        'name',
        'xsrf_token',
        'session_cookie',
        'session_cookie_name',
        'fetched_at',
    ];

    protected $casts = [
        'fetched_at' => 'datetime',

        // xsrf_token va session_cookie amalda parolga teng kuchli maxfiy
        // ma'lumot hisoblanadi (ular orqali sessiyani "o'g'irlab" foydalanish
        // mumkin), shuning uchun bazada APP_KEY bilan shifrlangan holda saqlanadi.
        'xsrf_token' => 'encrypted',
        'session_cookie' => 'encrypted',
    ];
}
