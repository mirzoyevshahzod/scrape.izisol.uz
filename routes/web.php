<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EomborScrapeController;
use App\Http\Controllers\ExampleController;
use App\Http\Controllers\MintransController;
use App\Http\Controllers\ScrapeController;
use App\Http\Controllers\TurkeyScraperController;
use App\Http\Controllers\API\QozoqController;
use App\Http\Controllers\ZiticController;
use App\Http\Controllers\ZanjeerController;

// Default index route
Route::get('/', function () {
    return view('index');
})->name('home');

// Eombor scraping routes
Route::get('/scrape-eombor', [EomborScrapeController::class, 'index'])->name('scrape.eombor');
Route::post('/scrape-eombor', [EomborScrapeController::class, 'scrape'])->name('scrape.eombor.process');

//Mintrans license
Route::get('/mintrans-litsenziya', [HomeController::class, 'mintrans'])->name('index');
//Mintrans Avtoraqam
Route::get('/mintrans-auto', [HomeController::class, 'mintransAuto'])->name('upload.index');
//Belarus scraping
Route::get('/belarus', [HomeController::class, 'belarus'])->name('scrape.data');
//Qozoq scraping
Route::get('/qozoq', [QozoqController::class, 'index'])->name('qozoq.index');
Route::get('/check-qozoq', [HomeController::class, 'checkQozoq'])->name('check.qozoq');
//Zitic scraping
Route::get('/zitic', [HomeController::class, 'zitic'])->name('zitic.index');

//Orginfo scraping
Route::get('/orginfo', [HomeController::class, 'orginfo'])->name('orginfo.index');

//Zanjeer scraping
Route::get('/zanjeer', [HomeController::class, 'scrapeZanjeer'])->name('zanjeer.index');
// turkey scraper routes
Route::get('/turkey', [HomeController::class, 'turkey'])->name('turkey.index');
Route::get('/autos', [HomeController::class, 'autos'])->name('autos.index');

Route::get('/upload-zanjeer-file', [HomeController::class, 'uploadZanjeer'])->name('upload-zanjeer');

Route::get('/e-ombor-converter', [HomeController::class, 'eOmborConverter'])->name('eOmborConverter');

Route::get('/turkiya-converter', [HomeController::class, 'turkiyaConverter'])->name('turkiyaConverter');

Route::get('/zitic-converter', [HomeController::class, 'ziticConverter'])->name('ziticConverter');

Route::get('/declarant-converter', [HomeController::class, 'declarantConverter'])->name('declarantConverter');