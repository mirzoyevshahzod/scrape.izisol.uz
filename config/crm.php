<?php

return [

    /*
    |--------------------------------------------------------------------
    | EGS CRM integratsiya API'si (App\Services\EgsCrmClient)
    |--------------------------------------------------------------------
    |
    | "Торнадо" importi, buyurtmalar (raqam bo'yicha menejer) va
    | kontragentlar (kompaniya bo'yicha mas'ul xodim). Kalit faqat .env'da
    | (EGS_CRM_API_KEY) — shu faylga yozmang.
    |
    */

    'egs' => [
        'base_url' => env('EGS_CRM_API_URL', 'https://crm.zanjeer.uz/api/v1/integration'),
        'api_key' => env('EGS_CRM_API_KEY'),
    ],
];
