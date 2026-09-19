<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------
| Kunlik pipeline — har bir bosqich orasida 1 soat
|--------------------------------------------------------------------
| 05:00 — Zanjeer CRM'ga login (token yangilanadi)
| 06:00 — barcha scrape buyruqlari (Qoldau, declarant, zitic, turkey)
| 07:00 — Qoldau warehouse import/export/merge pipeline
| 08:00 — Zanjeer CRM'ga import (custom-operations)
*/

Schedule::command('crm:login')
    ->dailyAt('05:00')
    ->withoutOverlapping();

$qoldauCheckpoints = [
    'Kazakhstan - Russia',
    'Kazakhstan - Uzbekistan',
    'Kazakhstan - China',
    'Kazakhstan - Kyrgyzstan',
];

foreach ($qoldauCheckpoints as $checkpoint) {
    Schedule::command('qoldau:scrape', [$checkpoint])
        ->dailyAt('06:00')
        ->withoutOverlapping();
}

$declarantZones = ['benyakoni', 'kamennii-log', 'kozlovichi'];

foreach ($declarantZones as $zone) {
    Schedule::command('scrape:declarant-data', [$zone])
        ->dailyAt('06:00')
        ->withoutOverlapping();
}

Schedule::command('scrape:zitic-html')
    ->dailyAt('06:00')
    ->withoutOverlapping();

Schedule::command('scrape:html')
    ->dailyAt('06:00')
    ->withoutOverlapping();

Schedule::command('qozoq:warehouse-pipeline')
    ->dailyAt('07:00')
    ->withoutOverlapping();

Schedule::command('crm:daily-import')
    ->dailyAt('08:00')
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------
| Zanjeer CRM sessiyasini kun davomida yangilab turish
|--------------------------------------------------------------------
| scrape:zanjeer-operators har doim yangi token topishi uchun.
*/
Schedule::command('crm:login')
    ->hourly()
    ->withoutOverlapping();
