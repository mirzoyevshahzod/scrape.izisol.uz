<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use App\Jobs\RunMintransScrapeJob;
use Illuminate\Support\Str;

class ExampleController extends Controller
{
    public function index()
    {
        set_time_limit(0);
        Log::info('Mintrans scraping sahifasi yuklandi');
        return view('mintrans_scrape');
    }

   


}