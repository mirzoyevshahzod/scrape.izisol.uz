<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;

class RunDeclarantScrapeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $region;
    protected $url;
    protected $name;
    protected $jobId;

    /**
     * Yangi job yaratish
     */
    public function __construct($region, $url, $name, $jobId)
    {
        $this->region = $region;
        $this->url = $url;
        $this->name = $name;
        $this->jobId = $jobId;
    }

    /**
     * Queue ishga tushganda bajariladigan kod
     */
    public function handle()
    {
        try {
            Log::info('RunDeclarantScrapeJob: Scraping boshlanmoqda', [
                'region' => $this->region,
                'url' => $this->url,
                'name' => $this->name,
                'jobId' => $this->jobId,
            ]);

            Artisan::call('scrape:declarant', [
                'region' => $this->region,
                '--url' => $this->url,
                '--name' => $this->name,
                '--jobId' => $this->jobId,
            ]);

            Log::info('RunDeclarantScrapeJob: Scraping tugadi', [
                'output' => Artisan::output(),
            ]);

        } catch (\Exception $e) {
            Log::error('RunDeclarantScrapeJob: Xato yuz berdi', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
