<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Mintrans Litsenziya sahifasi olib tashlandi — uning ruxsati ham kerak emas.
 * Rollarga biriktirilgan yozuvlar (role_has_permissions) foreign key orqali o'chadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::where('name', 'page.mintrans-license')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::firstOrCreate(['name' => 'page.mintrans-license', 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
