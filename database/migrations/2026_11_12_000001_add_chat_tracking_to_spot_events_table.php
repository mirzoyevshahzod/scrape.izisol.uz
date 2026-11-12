<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * spot:events bir nechta chatga yuboradi: e'lon/eslatma qaysi chatlarga
 * yetib borganini saqlaymiz, xato bo'lsa faqat qolganlariga qayta yuborish uchun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spot_events', function (Blueprint $table) {
            $table->json('notified_chats')->nullable()->after('notified_at');
            $table->json('reminded_chats')->nullable()->after('reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('spot_events', function (Blueprint $table) {
            $table->dropColumn(['notified_chats', 'reminded_chats']);
        });
    }
};
