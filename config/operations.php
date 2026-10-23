<?php

/*
|--------------------------------------------------------------------
| "Boshqaruv" sahifasidagi amallar
|--------------------------------------------------------------------
|
| Har bir amal queue orqali (RunOperationJob, "operations" navbati)
| ketma-ket ishga tushiriladigan artisan buyruqlari ro'yxati.
|
| 'date' => true bo'lgan amallar ishga tushirishdan oldin kunni so'raydi;
| tanlangan kun har bir buyruqqa --date=YYYY-MM-DD sifatida beriladi.
|
| Qoldau checkpoint'lari va declarant zonalari routes/console.php'dagi
| 06:00 jadvali bilan umumiy — "Barcha scraping" tugmasi aynan jadvaldagi
| buyruqlarni ishga tushiradi.
|
*/

$qoldauCheckpoints = [
    'Kazakhstan - Russia',
    'Kazakhstan - Uzbekistan',
    'Kazakhstan - China',
    'Kazakhstan - Kyrgyzstan',
];

$declarantZones = ['benyakoni', 'kamennii-log', 'kozlovichi'];

return [

    'qoldau_checkpoints' => $qoldauCheckpoints,

    'declarant_zones' => $declarantZones,

    'operations' => [
        'crm-login' => [
            'label' => 'CRM login',
            'description' => 'Zanjeer CRM\'ga kirib, sessiya tokenlarini yangilaydi.',
            'icon' => 'fa-key',
            'permission' => 'operation.crm-login',
            'commands' => [
                ['crm:login', []],
            ],
        ],

        'pipeline' => [
            'label' => 'Pipeline',
            'description' => 'Tanlangan kun fayllari bilan warehouse import/export, merge va yakuniy fayl (qozoq:warehouse-pipeline).',
            'icon' => 'fa-project-diagram',
            'permission' => 'operation.pipeline',
            'date' => true,
            'commands' => [
                ['qozoq:warehouse-pipeline', []],
            ],
        ],

        'telegram' => [
            'label' => 'Telegram\'ga yuborish',
            'description' => 'Tanlangan kun fayllarini Manager ustuni bilan Telegram chat\'larga yuboradi. Bugun yuborilganlar takrorlanmaydi.',
            'icon' => 'fa-paper-plane',
            'permission' => 'operation.telegram',
            'date' => true,
            'commands' => [
                ['telegram:send-daily-files', []],
            ],
        ],

        'crm-import' => [
            'label' => 'CRM import',
            'description' => 'Tanlangan kun fayllarini (Kazakhstan-Russia, Turkey, Zitic, Declarant) formatlab, Zanjeer CRM\'ga import qiladi (crm:daily-import).',
            'icon' => 'fa-file-import',
            'permission' => 'operation.crm-import',
            'date' => true,
            'commands' => [
                ['crm:daily-import', []],
            ],
        ],

        'scraping' => [
            'label' => 'Barcha scraping',
            'description' => 'Qoldau (4 ta), Declarant (3 ta), Zitic va Turkiya scraping\'ini ketma-ket ishga tushiradi.',
            'icon' => 'fa-spider',
            'permission' => 'operation.scraping',
            'commands' => [
                ...array_map(fn ($checkpoint) => ['qoldau:scrape', ['checkpoint' => $checkpoint]], $qoldauCheckpoints),
                ...array_map(fn ($zone) => ['scrape:declarant-data', ['zone' => $zone]], $declarantZones),
                ['scrape:zitic-html', []],
                ['scrape:html', []],
            ],
        ],
    ],

];
