<?php

namespace App\Support;

/**
 * Tizimdagi barcha permission'larning yagona ro'yxati.
 *
 * `page.*` — sahifaning o'zi va u chaqiradigan /api/* endpoint'lar
 * (routes/web.php va routes/api.php'da `permission:` middleware orqali).
 * Bir nechta sahifa ishlatadigan endpoint'lar shu sahifalarning istalgan
 * birining permission'i bilan ochiladi (`permission:a|b`).
 *
 * `PermissionSeeder` shu ro'yxat bo'yicha bazani to'ldiradi; yangi sahifa
 * qo'shilsa, permission'ini shu yerga yozib, seeder'ni qayta ishga
 * tushirish kifoya. super_admin barcha permission'larga avtomatik ega.
 */
final class Permissions
{
    /** @var array<string,array<string,string>> guruh => [permission => yorliq] */
    public const GROUPS = [
        'Sahifalar' => [
            'page.autos' => 'Autos',
            'page.mintrans-auto' => 'Mintrans Avtoraqam',
            'page.qozoq' => 'Qozoq Scraper',
            'page.check-qozoq' => 'Qozoq Tekshirilgan',
            'page.belarus' => 'Belarus Litva',
            'page.turkey' => 'Turkiya Gruziya',
            'page.zitic' => 'Zitic Scraper',
            'page.zanjeer' => 'Zanjeer Scraper',
            'page.orginfo' => 'Orginfo Scraper',
            'page.upload-zanjeer' => 'Qozoq Tekshirilgan Converter',
            'page.eombor-converter' => 'E Ombor Converter',
            'page.turkiya-converter' => 'Turkiya Converter',
            'page.zitic-converter' => 'Zitic Converter',
            'page.declarant-converter' => 'Declarant Converter',
            'page.uzbek-plates' => 'Uzbek Car Number Filter',
            'page.merge-excel' => 'Excel Fayllarni Birlashtirish',
        ],
        'Boshqaruv amallari' => [
            'operation.crm-login' => 'CRM login',
            'operation.pipeline' => 'Pipeline\'ni ishga tushirish',
            'operation.telegram' => 'Telegram\'ga yuborish',
            'operation.crm-import' => 'CRM\'ga kunlik import',
            'operation.scraping' => 'Barcha scraping\'ni ishga tushirish',
        ],
        'Administrator' => [
            'admin.users' => 'Foydalanuvchilarni boshqarish',
            'admin.roles' => 'Rollarni boshqarish',
        ],
    ];

    /** @return array<string,string> permission => yorliq */
    public static function all(): array
    {
        return array_merge(...array_values(self::GROUPS));
    }
}
