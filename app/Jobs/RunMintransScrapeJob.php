<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RunMintransScrapeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $startId;
    public int $count;
    public int $endId;

    public string $fileName;

    /**
     * Create a new job instance.
     */
    public function __construct(int $startId, int $count, int $endId, string $fileName)
    {
        $this->startId = $startId;
        $this->count = $count;
        $this->endId = $endId;
        $this->fileName = $fileName;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('ProcessMintransLicenseJob: Job boshlandi', [
                'start_id' => $this->startId,
                'count' => $this->count,
                'end_id' => $this->endId,
                'file_name' => $this->fileName
            ]);

            $exitCode = Artisan::call('scrape:mintrans:license', [
                'start_id' => $this->startId,
                'count' => $this->count,
                'end_id' => $this->endId,
                'file_name' => $this->fileName
            ]);

            Log::info('ProcessMintransLicenseJob: Command tugadi', [
                'exit_code' => $exitCode,
                'output' => Artisan::output()
            ]);

        } catch (\Exception $e) {
            Log::error('ProcessMintransLicenseJob: Xato yuz berdi', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
