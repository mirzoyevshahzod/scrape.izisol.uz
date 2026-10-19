<?php

/*
|--------------------------------------------------------------------
| "Boshqaruv" sahifasidagi amallar
|--------------------------------------------------------------------
|
| Har bir amal queue orqali (RunOperationJob, "operations" navbati)
| ketma-ket ishga tushiriladigan artisan buyruqlari ro'yxati.
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
            'description' => 'Warehouse import/export, merge va yakuniy fayl (qozoq:warehouse-pipeline).',
            'icon' => 'fa-project-diagram',
            'permission' => 'operation.pipeline',
            'commands' => [
                ['qozoq:warehouse-pipeline', []],
            ],
        ],

        'telegram' => [
            'label' => 'Telegram\'ga yuborish',
            'description' => 'Bugungi fayllarni Manager ustuni bilan Telegram chat\'larga yuboradi. Avval yuborilganlar takrorlanmaydi.',
            'icon' => 'fa-paper-plane',
            'permission' => 'operation.telegram',
            'commands' => [
                ['telegram:send-daily-files', []],
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
