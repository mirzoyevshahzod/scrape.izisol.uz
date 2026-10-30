<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * CRM bilan endi API (X-Api-Key) orqali ishlanadi — Selenium login va uning
 * cookie'lari (crm_tokens) kerak emas. "CRM login" amali "CRM API tekshiruvi"
 * bo'ldi: ruxsat nomi o'zgaradi, rollarga biriktirilgani saqlanadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::where('name', 'operation.crm-login')->where('guard_name', 'web')->update(['name' => 'operation.crm-check']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Schema::dropIfExists('crm_tokens');
    }

    public function down(): void
    {
        Permission::where('name', 'operation.crm-check')->where('guard_name', 'web')->update(['name' => 'operation.crm-login']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Schema::create('crm_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('xsrf_token')->nullable();
            $table->text('session_cookie')->nullable();
            $table->string('session_cookie_name')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });
    }
};
