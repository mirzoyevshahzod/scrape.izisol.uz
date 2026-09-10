<?php

namespace App\Http\Controllers;

use App\Jobs\RunArtisanCommandJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class CheckpointScraperController extends Controller
{
    /**
     * Bitta request kelganda 4 ta scraping commandni
     * ketma-ket (chain) tartibda queuega qo'shadi.
     *
     * POST /api/checkpoints/scrape-all
     */
    public function scrapeAll(): JsonResponse
    {
        Bus::chain([
            new RunArtisanCommandJob('qozoq:scrape'),
            new RunArtisanCommandJob('scrape:belarus-queue'),
            new RunArtisanCommandJob('scrape:zitic-command'),
            new RunArtisanCommandJob('scrape:turkey-command'),
        ])
            ->onQueue('checkpoint-scrapers')
            ->catch(function (\Throwable $e) {
                Log::error(
                    "[Scraper Chain] Chain to'xtadi: " . $e->getMessage()
                );
            })
            ->dispatch();

        return response()->json([
            'message' => "Barcha 4 ta scraping command navbatga (queue) qo'shildi. Ketma-ket ishga tushadi.",
        ]);
    }
}