<?php

return [

    /*
    |--------------------------------------------------------------------
    | Zanjeer CRM sozlamalari
    |--------------------------------------------------------------------
    |
    | Bu qiymatlar .env faylidan olinadi. Haqiqiy parolni hech qachon
    | to'g'ridan-to'g'ri shu faylga yozmang — faqat .env orqali bering.
    |
    */

    'zanjeer' => [
        'base_url' => env('CRM_BASE_URL', 'https://crm.zanjeer.uz'),

        'login_url' => env(
            'CRM_LOGIN_URL',
            rtrim(env('CRM_BASE_URL', 'https://crm.zanjeer.uz'), '/') . '/login'
        ),

        // "Границы -> Торнадо" ro'yxati (custom-operations) sahifasi —
        // shu yerdagi "Импортировать" oynasi orqali Excel fayllar yuklanadi.
        'custom_operations_url' => env(
            'CRM_CUSTOM_OPERATIONS_URL',
            rtrim(env('CRM_BASE_URL', 'https://crm.zanjeer.uz'), '/') . '/users/custom-operations'
        ),

        'email' => env('CRM_LOGIN_EMAIL'),
        'password' => env('CRM_LOGIN_PASSWORD'),

        // Laravel odatda session cookie nomini APP_NAME asosida generatsiya qiladi
        // (masalan "zanjeer_crm_session"). config('session.cookie') qiymatiga
        // qarab to'g'ri nomni shu yerda ko'rsating.
        'session_cookie_name' => env('CRM_SESSION_COOKIE_NAME', 'zanjeer_crm_session'),

        // chromedriver allaqachon ishlab turgan bo'lsa shu manzilga ulanadi,
        // aks holda buyruq o'zi vaqtincha ishga tushiradi va oxirida to'xtatadi.
        'chromedriver_url' => env('CHROMEDRIVER_URL', 'http://127.0.0.1:9515'),
        'chromedriver_binary' => env('CHROMEDRIVER_BINARY', '/usr/bin/chromedriver'),

        // Chromium yoki Google Chrome binary yo'li. Bo'sh qoldirilsa,
        // Selenium/ChromeDriver tizimdagi standart Chrome'ni o'zi topishga harakat qiladi.
        'chrome_binary' => env('CHROME_BINARY'),
    ],

];
