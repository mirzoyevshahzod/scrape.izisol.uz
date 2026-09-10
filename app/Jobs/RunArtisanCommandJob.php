<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RunArtisanCommandJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Scraping uzoq davom etishi mumkin, shuning uchun qayta urinishlarni
     * cheklab qo'yamiz va timeoutni cheksiz qilamiz.
     */
    public int $tries = 1;
    public int $timeout = 0;

    public function __construct(
        public string $command,
        public array $parameters = []
    ) {
        $this->onQueue('checkpoint-scrapers');
    }

    public function handle(): void
    {
        Log::info("[Scraper Chain] Boshlandi: {$this->command}");

        try {
            Artisan::call($this->command, $this->parameters);

            Log::info("[Scraper Chain] Tugadi: {$this->command}");
            Log::info(Artisan::output());

        } catch (\Throwable $e) {

            Log::error("[Scraper Chain] Xato ({$this->command}): " . $e->getMessage());

            /*
             * Xatoni yuqoriga otamiz -> chain->catch() ishga tushadi va
             * qolgan commandlar bajarilmaydi (bir-biriga bog'liq bo'lsa foydali).
             *
             * Agar 4 ta command bir-biridan mutlaqo mustaqil bo'lib,
             * biri qulasa ham qolganlari davom etishini istasangiz,
             * shu "throw $e;" qatorini olib tashlang.
             */
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error(
            "[Scraper Chain] JOB FAILED: {$this->command} - " .
            $exception->getMessage()
        );
    }
}