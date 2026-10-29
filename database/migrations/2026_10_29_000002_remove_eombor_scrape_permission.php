<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * E-Ombor Scraping sahifasi olib tashlandi — uning ruxsati ham kerak emas.
 * (E-Ombor Converter — boshqa sahifa, page.eombor-converter qoladi.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::where('name', 'page.eombor-scrape')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::firstOrCreate(['name' => 'page.eombor-scrape', 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
