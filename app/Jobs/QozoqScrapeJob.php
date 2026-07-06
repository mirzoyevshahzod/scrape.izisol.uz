<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class QozoqScrapeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $checkpoint;

    public int $timeout = 7200; // 2 soat
    public int $tries = 1;

    public function __construct($checkpoint)
    {
        $this->checkpoint = $checkpoint;
        $this->onQueue('qoldau');
    }

    public function handle(): void
    {
        Log::info('QozoqScrapeJob STARTED', [
            'checkpoint' => $this->checkpoint,
        ]);

        try {

            $exitCode = Artisan::call('qoldau:scrape', [
                'checkpoint' => $this->checkpoint,
            ]);

            Log::info('QozoqScrapeJob FINISHED', [
                'exit_code' => $exitCode,
                'output'    => Artisan::output(),
            ]);

        } catch (\Exception $e) {

            Log::error('QozoqScrapeJob ERROR', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}