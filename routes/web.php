<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EomborScrapeController;
use App\Http\Controllers\ExampleController;
use App\Http\Controllers\MintransController;
use App\Http\Controllers\ScrapeController;
use App\Http\Controllers\TurkeyScraperController;

// Default index route
Route::get('/', function () {
    return view('index');
})->name('home');

// Eombor scraping routes
Route::get('/scrape-eombor', [EomborScrapeController::class, 'index'])->name('scrape.eombor');
Route::post('/scrape-eombor', [EomborScrapeController::class, 'scrape'])->name('scrape.eombor.process');

//Mintrans license
Route::get('/mintrans-litsenziya', [ExampleController::class, 'index'])->name('index');
Route::post('/submit', [ExampleController::class, 'submit'])->name('submit');

//Mintrans Avtoraqam
Route::get('/mintrans-auto', [MintransController::class, 'index'])->name('upload.index');
Route::post('/upload', [MintransController::class, 'upload'])->name('upload.store');

//Belarus scraping
Route::get('/belarus', [ScrapeController::class, 'index'])->name('scrape.data');
Route::post('/belarus', [ScrapeController::class, 'scrape'])->name('scrape');
Route::get('/belarus/details/{id}', [ScrapeController::class, 'getBelarusDetails'])->name('belarus.details');

// Turkey scraper routes
Route::get('/turkey', [TurkeyScraperController::class, 'index'])->name('turkey.index');
Route::post('/turkey/scrape', [TurkeyScraperController::class, 'scrape'])->name('turkey.scrape');
Route::get('/turkey/status', [TurkeyScraperController::class, 'checkScrapingStatus'])->name('turkey.status');
Route::get('/turkey/download/{file}', [TurkeyScraperController::class, 'downloadFile'])->name('turkey.download');
Route::get('/turkey/details/{id}', [TurkeyScraperController::class, 'getDetails'])->name('turkey.details');
