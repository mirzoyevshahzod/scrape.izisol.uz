<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TurkeyData extends Model
{
    protected $table = 'turkey_data';

    protected $fillable = [
        'sira',
        'giris',
        'plaka',
        'tarih',
        'yer',
        'rusumi',
        'yuk_qobiliyati',
        'license',
        'state_number',
        'company',
        'phone_number',
        'faoliyat_turi',
        'transport_turi',
        'yuk_turi',
        'berilgan_sana',
        'amal_muddati',
        'holati',
        'hududiy_boshqarma',
    ];
}