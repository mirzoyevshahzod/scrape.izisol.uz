<?php

use App\Support\Telegram\PipelineStatus;
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
| 06:00 — barcha scrape buyruqlari (Qoldau, declarant, zitic, turkey)
| 07:00 — Qoldau warehouse import/export/merge pipeline
| 08:00 — EGS CRM'ga import (custom-operations, API orqali)
| 08:45 — kunlik fayllarni Telegram'ga yuborish
| 09:00 — kunlik pipeline holati haqida Telegram'ga hisobot
*/

$qoldauCheckpoints = config('operations.qoldau_checkpoints');

foreach ($qoldauCheckpoints as $checkpoint) {
    Schedule::command('qoldau:scrape', [$checkpoint])
        ->dailyAt('06:00')
        ->withoutOverlapping()
        ->onFailure(fn () => PipelineStatus::recordFailure(
            "qoldau:scrape [{$checkpoint}]",
            "Muvaffaqiyatsiz tugadi, batafsil: storage/logs/laravel-" . now()->format('Y-m-d') . '.log'
        ));
}

$declarantZones = config('operations.declarant_zones');

foreach ($declarantZones as $zone) {
    Schedule::command('scrape:declarant-data', [$zone])
        ->dailyAt('06:00')
        ->withoutOverlapping()
        ->onFailure(fn () => PipelineStatus::recordFailure(
            "scrape:declarant-data [{$zone}]",
            "Muvaffaqiyatsiz tugadi, batafsil: storage/logs/laravel-" . now()->format('Y-m-d') . '.log'
        ));
}

Schedule::command('scrape:zitic-html')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->onFailure(fn () => PipelineStatus::recordFailure(
        'scrape:zitic-html (06:00)',
        "Muvaffaqiyatsiz tugadi, batafsil: storage/logs/laravel-" . now()->format('Y-m-d') . '.log'
    ));

Schedule::command('scrape:html')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->onFailure(fn () => PipelineStatus::recordFailure(
        'scrape:html (06:00)',
        "Muvaffaqiyatsiz tugadi, batafsil: storage/logs/laravel-" . now()->format('Y-m-d') . '.log'
    ));

Schedule::command('qozoq:warehouse-pipeline')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->onFailure(fn () => PipelineStatus::recordFailure(
        'qozoq:warehouse-pipeline (07:00)',
        "Muvaffaqiyatsiz tugadi, batafsil: storage/logs/laravel-" . now()->format('Y-m-d') . '.log'
    ));

Schedule::command('crm:daily-import')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onFailure(fn () => PipelineStatus::recordFailure(
        'crm:daily-import (08:00)',
        "Muvaffaqiyatsiz tugadi, batafsil: storage/logs/laravel-" . now()->format('Y-m-d') . '.log'
    ));

Schedule::command('telegram:send-daily-files')
    ->dailyAt('08:45')
    ->withoutOverlapping();

Schedule::command('telegram:daily-status')
    ->dailyAt('09:00')
    ->withoutOverlapping();
