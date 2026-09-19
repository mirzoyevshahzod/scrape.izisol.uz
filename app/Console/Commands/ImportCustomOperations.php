<?php

namespace App\Console\Commands;

use App\Support\Crm\CrmSession;
use Illuminate\Console\Command;
use Throwable;

/**
 * Berilgan Excel faylini Zanjeer CRM'dagi "Границы -> Торнадо"
 * (/users/custom-operations) sahifasidagi "Импортировать" oynasi orqali
 * yuklaydi.
 *
 * Fayl nomi (kengaytmasiz) qaysi "Граница"ga tegishli ekanini aniqlash
 * uchun ishlatiladi: masalan "Шарбакты - Кулунда.xlsx" fayli yuklansa,
 * combobox'dan avtomatik "Шарбакты - Кулунда" varianti tanlanadi.
 * Fayl nomi granitsa nomidan farq qilsa --border= bilan qo'lda ko'rsatish
 * mumkin.
 */
class ImportCustomOperations extends Command
{
    protected $signature = 'crm:import-custom-operations
                            {path : Excel faylining SERVERDAGI to\'liq (absolute) manzili}
                            {--border= : Granitsa nomini fayl nomidan olish o\'rniga qo\'lda ko\'rsatish}
                            {--email= : .env dagi CRM_LOGIN_EMAIL o\'rniga ishlatiladi}
                            {--password= : .env dagi CRM_LOGIN_PASSWORD o\'rniga ishlatiladi}
                            {--timeout=60 : Har bir bosqich uchun kutish vaqti (soniya)}
                            {--fresh : chromedriver allaqachon ishlab tursa ham, yangisini ishga tushirish}';

    protected $description = 'Excel faylni fayl nomiga mos "Граница"ni tanlab, custom-operations (Торнадо) importiga yuklaydi';

    public function handle(): int
    {
        $config = config('crm.zanjeer');

        $path = $this->argument('path');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("Fayl topilmadi yoki o'qib bo'lmaydi: {$path}");

            return self::FAILURE;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($extension, ['xlsx', 'xls'], true)) {
            $this->error("Fayl kengaytmasi .xlsx yoki .xls bo'lishi kerak, berilgan: .{$extension}");

            return self::FAILURE;
        }

        $borderName = trim($this->option('border') ?: pathinfo($path, PATHINFO_FILENAME));

        $email = $this->option('email') ?: $config['email'];
        $password = $this->option('password') ?: $config['password'];

        if (! $email || ! $password) {
            $this->error('CRM_LOGIN_EMAIL va CRM_LOGIN_PASSWORD .env faylida (yoki --email= / --password= bilan) berilishi shart.');

            return self::FAILURE;
        }

        $timeout = (int) $this->option('timeout');
        $session = null;

        try {
            $this->info('chromedriver va brauzer tayyorlanmoqda...');
            $session = CrmSession::start($config, (bool) $this->option('fresh'));

            $this->info('Tizimga kirilmoqda...');
            $session->login($config, $email, $password, $timeout);

            $this->info("Sahifa ochilmoqda: {$config['custom_operations_url']}");
            $this->info("Granitsa tanlanmoqda: {$borderName}");
            $this->info("Fayl biriktirilmoqda: {$path}");

            $session->importFile($config, $borderName, $path, $timeout);

            $this->info("'{$borderName}' uchun fayl muvaffaqiyatli import qilindi.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Xatolik yuz berdi: ' . $e->getMessage());

            return self::FAILURE;
        } finally {
            $session?->quit();
        }
    }
}
