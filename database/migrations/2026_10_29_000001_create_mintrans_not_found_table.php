<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mintrans'da ma'lumoti chiqmagan (yoki tekshirishda xato bo'lgan) avtoraqamlar.
 * Bitta raqam — bitta qator; har tekshiruvda yangilanadi, keyinroq topilsa o'chiriladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mintrans_not_found', function (Blueprint $table) {
            $table->id();
            $table->string('plate', 16)->unique();
            $table->string('status', 20)->index(); // not_found | error
            $table->string('message')->nullable();
            $table->foreignId('job_run_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('checks')->default(1);
            $table->timestamp('checked_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mintrans_not_found');
    }
};
