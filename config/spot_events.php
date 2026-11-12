<?php

/*
|--------------------------------------------------------------------------
| spot.uz tadbirlari (spot:events)
|--------------------------------------------------------------------------
| https://www.spot.uz/ru/events/ sahifasidan sarlavhasida kalit so'zlardan
| biri bor tadbirlarni alohida bot orqali chat_id'ga yuboradi, tadbirdan
| bir kun oldin esa eslatma yuboradi.
|
| Kalit so'zlar vergul bilan ajratiladi, katta-kichik harf, defis va
| bo'shliq farqi hisobga olinmaydi ("бизнес-завтрак" = "Бизнес завтрак"),
| so'z qismi ham mos keladi ("форум" → "бизнес-форум", "форума").
| .env'da o'zgartirgandan keyin: php artisan config:clear
*/

return [
    'bot_token' => env('SPOT_EVENTS_BOT_TOKEN'),
    'chat_id' => env('SPOT_EVENTS_CHAT_ID'),
    'keywords' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SPOT_EVENTS_KEYWORDS', 'B2B,форум,бизнес-завтрак'))
    ))),
];
