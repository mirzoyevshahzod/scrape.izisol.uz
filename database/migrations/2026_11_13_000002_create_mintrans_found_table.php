<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mintrans'da ma'lumoti topilgan avtoraqamlar va o'sha ma'lumot (litsenziyalar).
 * 1-bosqichda "avval topilganlarni qayta so'ramaslik" tanlansa, shu raqamlar
 * Mintrans'ga so'ralmaydi — ma'lumot shu yerdan natija Excel'iga yoziladi.
 * Bitta raqam — bitta qator; qayta so'ralganda yangilanadi, topilmay qolsa o'chiriladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mintrans_found', function (Blueprint $table) {
            $table->id();
            $table->string('plate', 16)->unique();
            $table->json('licenses'); // MintransClient::search() natijasi
            $table->foreignId('job_run_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('checks')->default(1);
            $table->timestamp('checked_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mintrans_found');
    }
};
