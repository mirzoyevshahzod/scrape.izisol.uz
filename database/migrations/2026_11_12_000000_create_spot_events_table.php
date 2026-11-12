<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * spot.uz/ru/events sahifasida ko'rilgan tadbirlar (spot:events).
 * notified_at — Telegram'ga e'lon yuborilgan vaqt (bitta tadbir ikki marta
 * yuborilmasligi uchun), reminded_at — "ertaga" eslatmasi yuborilgan vaqt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spot_events', function (Blueprint $table) {
            $table->id();
            $table->string('url')->unique();
            $table->string('title', 500);
            $table->string('category')->nullable();
            $table->string('city')->nullable();
            $table->string('price')->nullable();
            $table->string('date_text')->nullable();  // saytdagi ko'rinishi: "14 ноября (суббота)"
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('start_time', 5)->nullable(); // "10:00", sahifada vaqt berilgan bo'lsa
            $table->text('description')->nullable();
            $table->string('registration_url', 500)->nullable();
            $table->string('matched_keyword')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spot_events');
    }
};
