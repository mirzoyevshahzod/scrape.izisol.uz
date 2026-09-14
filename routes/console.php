<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$qoldauCheckpoints = [
    'Kazakhstan - Russia',
    'Kazakhstan - Uzbekistan',
    'Kazakhstan - China',
    'Kazakhstan - Kyrgyzstan',
];

foreach ($qoldauCheckpoints as $checkpoint) {
    Schedule::command('qoldau:scrape', [$checkpoint])
        ->dailyAt('08:00')
        ->withoutOverlapping();
}

$declarantZones = ['benyakoni', 'kamennii-log', 'kozlovichi'];

foreach ($declarantZones as $zone) {
    Schedule::command('scrape:declarant-data', [$zone])
        ->dailyAt('08:00')
        ->withoutOverlapping();
}

Schedule::command('scrape:zitic-html')
    ->dailyAt('08:00')
    ->withoutOverlapping();

Schedule::command('scrape:html')
    ->dailyAt('08:00')
    ->withoutOverlapping();

Schedule::command('qozoq:warehouse-pipeline')
    ->dailyAt('09:00')
    ->withoutOverlapping();
