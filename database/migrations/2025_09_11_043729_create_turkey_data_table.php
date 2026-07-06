<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turkey_data', function (Blueprint $table) {
            $table->id();
            $table->string('sira')->nullable();
            $table->string('giris')->nullable();
            $table->string('plaka')->nullable();
            $table->string('tarih')->nullable();
            $table->string('yer')->nullable();
            $table->string('rusumi')->nullable();
            $table->string('yuk_qobiliyati')->nullable();
            $table->string('license')->nullable();
            $table->string('state_number')->nullable();
            $table->string('company')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('faoliyat_turi')->nullable();
            $table->string('transport_turi')->nullable();
            $table->string('yuk_turi')->nullable();
            $table->string('berilgan_sana')->nullable();
            $table->string('amal_muddati')->nullable();
            $table->string('holati')->nullable();
            $table->string('hududiy_boshqarma')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turkey_data');
    }
};