<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_runs', function (Blueprint $table) {
            // Ishga tushirishda tanlangan parametrlar, masalan {"date": "2026-10-20"}
            $table->json('params')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('job_runs', function (Blueprint $table) {
            $table->dropColumn('params');
        });
    }
};
