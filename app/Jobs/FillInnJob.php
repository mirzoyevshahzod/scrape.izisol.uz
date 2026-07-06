<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class FillInnJob implements ShouldQueue
{
    use Queueable;

    public string $inputPath;
    public string $outputPath;

    public int $timeout = 3600;
    public int $tries = 1;

    public function __construct($inputPath, $outputPath)
    {
        $this->inputPath = $inputPath;
        $this->outputPath = $outputPath;
    }

    public function handle(): void
    {
        Log::info('FillInnJob STARTED', [
            'input'  => $this->inputPath,
            'output' => $this->outputPath,
        ]);

        try {

            $exitCode = Artisan::call('excel:fill-inn', [
                'file'   => $this->inputPath,
                'output' => $this->outputPath,
            ]);

            Log::info('FillInnJob FINISHED', [
                'exit_code' => $exitCode,
                'output'    => Artisan::output(),
            ]);

        } catch (\Exception $e) {

            Log::error('FillInnJob ERROR', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}