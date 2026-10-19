<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * `super_admin` rolini va shu rolga ega bitta foydalanuvchini yaratadi.
 * Ma'lumotlar .env dan olinadi (SUPER_ADMIN_EMAIL, SUPER_ADMIN_PASSWORD,
 * SUPER_ADMIN_NAME) — parol kodda saqlanmaydi.
 *
 * Qayta ishga tushirilsa foydalanuvchi ikkinchi marta yaratilmaydi va
 * mavjud parol o'zgartirilmaydi, faqat rol biriktirilganiga ishonch hosil
 * qilinadi.
 *
 *   php artisan db:seed --class=SuperAdminSeeder
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $email = env('SUPER_ADMIN_EMAIL');
        $password = env('SUPER_ADMIN_PASSWORD');

        if (! $email || ! $password) {
            throw new RuntimeException('.env faylida SUPER_ADMIN_EMAIL va SUPER_ADMIN_PASSWORD berilishi shart.');
        }

        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => env('SUPER_ADMIN_NAME', 'Super Admin'), 'password' => $password]
        );

        $user->assignRole($role);

        $this->command?->info("super_admin: {$user->email}" . ($user->wasRecentlyCreated ? ' (yangi yaratildi)' : ' (mavjud edi, rol biriktirildi)'));
    }
}
