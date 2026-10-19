<?php

namespace Database\Seeders;

use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * App\Support\Permissions ro'yxatidagi permission'larni bazaga yozadi.
 * Qayta ishga tushirish xavfsiz — mavjudlari qayta yaratilmaydi, ro'yxatda
 * yo'q bo'lganlari o'chirilmaydi.
 *
 *   php artisan db:seed --class=PermissionSeeder
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $created = 0;

        foreach (array_keys(Permissions::all()) as $name) {
            $created += Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'])->wasRecentlyCreated ? 1 : 0;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('Permission\'lar: ' . count(Permissions::all()) . " ta, shundan {$created} tasi yangi.");
    }
}
