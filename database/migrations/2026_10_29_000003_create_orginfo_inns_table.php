<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * orginfo.uz'dan topilgan INN'lar keshi: bitta korxona nomi bo'yicha
 * orginfo'ga qayta-qayta murojaat qilmaslik uchun (3-bosqich, mintrans:inn).
 * inn = null — qidirilgan, lekin topilmagan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orginfo_inns', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // OOO "NOMI" ko'rinishidagi nom
            $table->string('inn', 20)->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orginfo_inns');
    }
};
