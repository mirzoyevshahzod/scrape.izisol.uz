<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ScrapeZanjeerJob implements ShouldQueue
{
    use Dispatchable,
        InteractsWithQueue,
        Queueable,
        SerializesModels;

    public string $path;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function handle(): void
    {
        Log::info('JOB START', [
            'path' => $this->path
        ]);

        $exitCode = Artisan::call(
            'scrape:zanjeer-operators',
            [
                'file' => $this->path
            ]
        );

        Log::info('JOB FINISHED', [
            'exit_code' => $exitCode,
            'output' => Artisan::output()
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('JOB FAILED', [
            'message' => $e->getMessage()
        ]);
    }
}