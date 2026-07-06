<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RunTurkeyScrapeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0; // cheklovsiz
    public $tries = 3;   // retry uchun

    /**
     * Fayl nomi (agar kerak bo‘lsa)
     *
     * @var string|null
     */
    protected $fileName;

    /**
     * Job yaratish
     *
     * @param string|null $fileName
     */
    public function __construct($fileName = null)
    {
        $this->fileName = $fileName;
    }

    /**
     * Jobni bajarish
     *
     * @return void
     */
    public function handle()
    {
        Log::info('RunTurkeyScrapeJob: Scraping boshlanmoqda', [
            'fileName' => $this->fileName
        ]);

        try {
            // Agar Artisan commandga --file opsiyasi kerak bo‘lsa:
            $params = [];
            if ($this->fileName) {
                $params['--file'] = $this->fileName;
            }

            // Artisan command chaqirish
            $exitCode = Artisan::call('scrape');
            $output = Artisan::output();

            Log::info('RunTurkeyScrapeJob: Artisan command tugadi', [
                'exit_code' => $exitCode,
                'output' => $output
            ]);

            if ($exitCode !== 0) {
                Log::error('RunTurkeyScrapeJob: Artisan command xato bilan tugadi', [
                    'exit_code' => $exitCode,
                    'output' => $output
                ]);
            }

        } catch (\Throwable $e) {
            Log::error('RunTurkeyScrapeJob: Xato yuz berdi', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
