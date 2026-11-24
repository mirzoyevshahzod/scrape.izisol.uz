<?php

/*
|--------------------------------------------------------------------
| Belarus navbati (mon.declarant.by) — captcha'ni odam yechadigan brauzer
|--------------------------------------------------------------------
| Serverdagi brauzer (deploy/belarus-queue-browser) captcha yechilgan
| sahifa olgan navbat javobini POST /api/belarus-queue ga yuboradi.
| belarus:queue-sync (har daqiqa) undan Excel tayyorlab CRM'ga yuklaydi,
| ma'lumot eskirsa — Telegram'ga captcha havolasi bilan eslatma yuboradi.
*/

return [

    // brauzer kengaytmasi X-Ingest-Token header'ida yuboradigan maxfiy kalit
    'ingest_token' => env('BELARUS_QUEUE_TOKEN'),

    // kuzatiladigan zonalar (App\Services\DeclarantQueue::ZONES kalitlari)
    'zones' => array_values(array_filter(array_map('trim', explode(',', (string) env('BELARUS_QUEUE_ZONES', 'benyakoni,kamennii-log'))))),

    // eslatmadagi tugma: masofaviy brauzer oynasi (noVNC) havolasi
    'vnc_url' => env('BELARUS_QUEUE_VNC_URL'),

    // zona ma'lumoti shuncha daqiqadan eski bo'lsa — eslatma
    'stale_minutes' => (int) env('BELARUS_QUEUE_STALE_MINUTES', 60),

    // eslatma ko'pi bilan shuncha daqiqada bir marta
    'remind_every_minutes' => (int) env('BELARUS_QUEUE_REMIND_EVERY', 60),

    // eslatma faqat shu soatlar oralig'ida (boshi kiradi, oxiri kirmaydi), masalan "8-22"; "0-24" — doim
    'remind_hours' => env('BELARUS_QUEUE_REMIND_HOURS', '8-22'),

];
