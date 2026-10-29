<?php

/*
|--------------------------------------------------------------------
| Chap menyu (layouts/app) va bosh sahifadagi bo'limlar kartalari
|--------------------------------------------------------------------
|
| Har bir band: route nomi, Font Awesome ikonka, permission (null — har
| qanday login qilgan foydalanuvchi uchun) va bosh sahifada ko'rinadigan
| qisqa tavsif. Foydalanuvchi faqat ruxsati bor bandlarni ko'radi.
|
*/

return [
    [
        'title' => null,
        'items' => [
            ['label' => 'Bosh sahifa', 'route' => 'home', 'icon' => 'fa-house', 'permission' => null],
            ['label' => 'Boshqaruv', 'route' => 'operations.index', 'icon' => 'fa-sliders', 'match' => 'operations.*',
                'permission' => ['operation.crm-login', 'operation.pipeline', 'operation.telegram', 'operation.crm-import', 'operation.scraping'],
                'description' => 'CRM login, pipeline, Telegram va scraping\'ni bir bosishda ishga tushirish.'],
        ],
    ],
    [
        'title' => 'Scraping',
        'items' => [
            ['label' => 'Qozoq Scraper', 'route' => 'qozoq.index', 'icon' => 'fa-truck-moving', 'permission' => 'page.qozoq',
                'description' => 'Qoldau checkpoint\'lari bo\'yicha navbatdagi mashinalar.'],
            ['label' => 'Qozoq Tekshirilgan', 'route' => 'check.qozoq', 'icon' => 'fa-clipboard-check', 'permission' => 'page.check-qozoq',
                'description' => 'Warehouse tekshirgan qozoq fayllarini yuklash va ko\'rish.'],
            ['label' => 'Belarus Litva', 'route' => 'scrape.data', 'icon' => 'fa-road', 'permission' => 'page.belarus',
                'description' => 'Declarant navbatlari: Benyakoni, Kamennii Log, Kozlovichi.'],
            ['label' => 'Turkiya Gruziya', 'route' => 'turkey.index', 'icon' => 'fa-flag', 'permission' => 'page.turkey',
                'description' => 'Turkiya–Gruziya chegarasidagi navbat ma\'lumotlari.'],
            ['label' => 'Zitic', 'route' => 'zitic.index', 'icon' => 'fa-route', 'permission' => 'page.zitic',
                'description' => 'Zitic.ru navbat ma\'lumotlarini yig\'ish.'],
            ['label' => 'Zanjeer', 'route' => 'zanjeer.index', 'icon' => 'fa-link', 'permission' => 'page.zanjeer',
                'description' => 'Fayllarni birlashtirish va Zanjeer operatorlarini qo\'shish.'],
            ['label' => 'Orginfo', 'route' => 'orginfo.index', 'icon' => 'fa-building', 'permission' => 'page.orginfo',
                'description' => 'Kompaniyalarga orginfo.uz\'dan INN qo\'shish.'],
            ['label' => 'Autos', 'route' => 'autos.index', 'icon' => 'fa-car', 'permission' => 'page.autos',
                'description' => 'Bazadagi avtomobillar ro\'yxati.'],
            ['label' => 'Mintrans Avtoraqam', 'route' => 'mintrans.index', 'match' => 'mintrans.*', 'icon' => 'fa-id-card', 'permission' => 'page.mintrans-auto',
                'description' => 'Excel\'dagi avtoraqamlar bo\'yicha Mintrans litsenziya ma\'lumotlari.'],
        ],
    ],
    [
        'title' => 'Konverterlar',
        'items' => [
            ['label' => 'Qozoq → Zanjeer', 'route' => 'upload-zanjeer', 'icon' => 'fa-file-export', 'permission' => 'page.upload-zanjeer',
                'description' => 'Tekshirilgan qozoq faylidan Zanjeer uchun fayllar.'],
            ['label' => 'E-Ombor Converter', 'route' => 'eOmborConverter', 'icon' => 'fa-file-excel', 'permission' => 'page.eombor-converter',
                'description' => 'E-Ombor fayllarini kerakli formatga o\'tkazish.'],
            ['label' => 'Turkiya Converter', 'route' => 'turkiyaConverter', 'icon' => 'fa-file-excel', 'permission' => 'page.turkiya-converter',
                'description' => 'Turkiya fayllarini CRM formatiga o\'tkazish.'],
            ['label' => 'Zitic Converter', 'route' => 'ziticConverter', 'icon' => 'fa-file-excel', 'permission' => 'page.zitic-converter',
                'description' => 'Zitic fayllarini CRM formatiga o\'tkazish.'],
            ['label' => 'Declarant Converter', 'route' => 'declarantConverter', 'icon' => 'fa-file-excel', 'permission' => 'page.declarant-converter',
                'description' => 'Belarus declarant fayllarini o\'tkazish.'],
            ['label' => 'O\'zbek raqam filtri', 'route' => 'filterUzbekCarNumber', 'icon' => 'fa-filter', 'permission' => 'page.uzbek-plates',
                'description' => 'Fayldan faqat O\'zbekiston raqamlarini ajratish.'],
            ['label' => 'Excel birlashtirish', 'route' => 'mergeExcelFiles', 'icon' => 'fa-object-group', 'permission' => 'page.merge-excel',
                'description' => 'Bir nechta Excel faylni bittaga birlashtirish.'],
        ],
    ],
    [
        'title' => 'Administrator',
        'items' => [
            ['label' => 'Foydalanuvchilar', 'route' => 'admin.users.index', 'icon' => 'fa-users', 'match' => 'admin.users.*', 'permission' => 'admin.users',
                'description' => 'Foydalanuvchilar va ularning rollari.'],
            ['label' => 'Rollar', 'route' => 'admin.roles.index', 'icon' => 'fa-user-shield', 'match' => 'admin.roles.*', 'permission' => 'admin.roles',
                'description' => 'Rollar va ruxsatlar.'],
        ],
    ],
];
