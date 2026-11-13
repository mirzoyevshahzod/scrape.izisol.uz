<?php

/*
|--------------------------------------------------------------------------
| spot.uz tadbirlari (spot:events)
|--------------------------------------------------------------------------
| https://www.spot.uz/ru/events/ sahifasidan sarlavhasida kalit so'zlardan
| biri bor tadbirlarni alohida bot orqali chat_id'ga yuboradi, tadbirdan
| bir kun oldin esa eslatma yuboradi.
|
| Kalit so'zlar admin panelda ("Spot.uz tadbirlari" sahifasi) boshqariladi
| (spot_event_keywords jadvali). Katta-kichik harf, defis va bo'shliq farqi
| hisobga olinmaydi ("бизнес-завтрак" = "Бизнес завтрак"), so'z qismi ham
| mos keladi ("форум" → "бизнес-форум", "форума").
|
| SPOT_EVENTS_CHAT_ID'ga bir nechta chat vergul bilan yozilishi mumkin:
| SPOT_EVENTS_CHAT_ID=111111,222222 — xabar har biriga yuboriladi.
| .env'da o'zgartirgandan keyin: php artisan config:clear
*/

return [
    'bot_token' => env('SPOT_EVENTS_BOT_TOKEN'),
    'chat_ids' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SPOT_EVENTS_CHAT_ID', ''))
    ))),
    // Faqat spot_event_keywords jadvalini birinchi marta to'ldirish uchun (eski .env qiymati)
    'initial_keywords' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SPOT_EVENTS_KEYWORDS', 'B2B,форум,бизнес-завтрак'))
    ))),
];
