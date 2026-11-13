<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * spot:events kalit so'zlari endi admin panelda ("Spot.uz tadbirlari"
 * sahifasi) boshqariladi. Boshlang'ich ro'yxat — avvalgi .env qiymati
 * (SPOT_EVENTS_KEYWORDS), u bo'lmasa B2B, форум, бизнес-завтрак.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spot_event_keywords', function (Blueprint $table) {
            $table->id();
            $table->string('keyword', 100);
            $table->timestamps();
        });

        // config cache'langan bo'lsa unda eski 'keywords' kaliti bo'ladi
        $keywords = config('spot_events.initial_keywords') ?: config('spot_events.keywords') ?: ['B2B', 'форум', 'бизнес-завтрак'];

        DB::table('spot_event_keywords')->insert(array_map(
            fn ($keyword) => ['keyword' => $keyword, 'created_at' => now(), 'updated_at' => now()],
            array_values(array_unique($keywords))
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('spot_event_keywords');
    }
};
