<?php

namespace App\Console\Commands;

use App\Services\EgsCrmClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Berilgan Excel faylini EGS CRM API orqali "Границы -> Торнадо"
 * (custom-operations) importiga yuklaydi.
 *
 * Fayl nomi (kengaytmasiz) qaysi "Граница"ga tegishli ekanini aniqlash
 * uchun ishlatiladi: masalan "Шарбакты - Кулунда.xlsx" fayli yuklansa,
 * "Шарбакты - Кулунда" granitsasi tanlanadi. Fayl nomi granitsa nomidan
 * farq qilsa --border= bilan nom yoki ID qo'lda ko'rsatiladi.
 */
class ImportCustomOperations extends Command
{
    protected $signature = 'crm:import-custom-operations
                            {path : Excel faylining SERVERDAGI to\'liq (absolute) manzili}
                            {--border= : Granitsa nomi yoki ID (bo\'sh bo\'lsa fayl nomidan olinadi)}';

    protected $description = 'Excel faylni fayl nomiga mos "Граница"ga EGS CRM API orqali custom-operations (Торнадо) importiga yuklaydi';

    public function handle(EgsCrmClient $crm): int
    {
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

        $border = trim($this->option('border') ?: pathinfo($path, PATHINFO_FILENAME));

        try {
            $borderId = ctype_digit($border) ? (int) $border : $crm->borderId($border);

            if ($borderId === null) {
                $this->error("'{$border}' granitsasi CRM'da topilmadi. Mavjudlari: " . implode(', ', array_column($crm->borders(), 'name')));

                return self::FAILURE;
            }

            $this->info("Granitsa: {$border} (ID {$borderId}); fayl yuklanmoqda: {$path}");

            $rows = $crm->importOperations($borderId, $path);

            $this->info("'{$border}' uchun fayl import qilindi: {$rows} ta qator.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Xatolik yuz berdi: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
