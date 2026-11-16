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
| Har soatda: scraping -> CRM import
|--------------------------------------------------------------------
| Har soat boshida barcha scrape buyruqlari (Qoldau, declarant, zitic,
| turkey) ishga tushadi, so'ng crm:daily-import yangi fayllarni EGS CRM'ga
| ("Торнадо") yuklaydi. schedule:run bir daqiqada due bo'lgan buyruqlarni
| shu yerda yozilgan tartibda ketma-ket bajaradi, shuning uchun CRM import
| aynan shu soatning scrape fayllarini oladi. CRM'ga qayta yuklash xavfsiz
| (mavjud yozuvlar yangilanadi), shuning uchun dublikat bo'lmaydi.
| withoutOverlapping: oldingi soat hali tugamagan bo'lsa, o'sha buyruq
| shu soat o'tkazib yuboriladi.
|
| Kunlik bosqichlar (eng oxirgi soatlik fayllar bilan):
| 07:00 — Qoldau warehouse import/export/merge pipeline
| 08:45 — kunlik fayllarni Telegram'ga yuborish
| 09:00 — kunlik pipeline holati haqida Telegram'ga hisobot
*/

$recordFailure = fn (string $step) => fn () => PipelineStatus::recordFailure(
    "{$step} (" . now()->format('H:00') . ')',
    "Muvaffaqiyatsiz tugadi, batafsil: storage/logs/laravel-" . now()->format('Y-m-d') . '.log'
);

foreach (config('operations.qoldau_checkpoints') as $checkpoint) {
    Schedule::command('qoldau:scrape', [$checkpoint])
        ->hourly()
        ->withoutOverlapping()
        ->onFailure($recordFailure("qoldau:scrape [{$checkpoint}]"));
}

foreach (config('operations.declarant_zones') as $zone) {
    Schedule::command('scrape:declarant-data', [$zone])
        ->hourly()
        ->withoutOverlapping()
        ->onFailure($recordFailure("scrape:declarant-data [{$zone}]"));
}

Schedule::command('scrape:zitic-html')
    ->hourly()
    ->withoutOverlapping()
    ->onFailure($recordFailure('scrape:zitic-html'));

Schedule::command('scrape:html')
    ->hourly()
    ->withoutOverlapping()
    ->onFailure($recordFailure('scrape:html'));

Schedule::command('crm:daily-import')
    ->hourly()
    ->withoutOverlapping()
    ->onFailure($recordFailure('crm:daily-import'));

Schedule::command('qozoq:warehouse-pipeline')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->onFailure($recordFailure('qozoq:warehouse-pipeline'));

Schedule::command('telegram:send-daily-files')
    ->dailyAt('08:45')
    ->withoutOverlapping();

Schedule::command('telegram:daily-status')
    ->dailyAt('09:00')
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------
| spot.uz tadbirlari — har 2 soatda
|--------------------------------------------------------------------
| Yangi kerakli tadbirlar e'loni va "ertaga" eslatmasi (eslatma faqat
| 09:00 dan keyin) SPOT_EVENTS_CHAT_ID'ga (config/spot_events.php).
| Muammo bo'lsa, buyruqning o'zi o'sha chatga ogohlantiradi.
*/

Schedule::command('spot:events')
    ->everyTwoHours()
    ->withoutOverlapping();
