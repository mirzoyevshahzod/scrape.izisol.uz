<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_tokens', function (Blueprint $table) {
            $table->id();

            // Bir nechta CRM/hisob uchun ishlatilishi mumkin bo'lgani uchun
            // noyob nom bilan ajratilgan (masalan: 'zanjeer_crm').
            $table->string('name')->unique();

            // encrypted cast orqali Model darajasida shifrlanadi (App\Models\CrmToken).
            $table->text('xsrf_token')->nullable();
            $table->text('session_cookie')->nullable();

            $table->string('session_cookie_name')->nullable();
            $table->timestamp('fetched_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_tokens');
    }
};
