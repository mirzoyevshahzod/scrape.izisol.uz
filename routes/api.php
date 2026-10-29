<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramBotController;
use App\Http\Controllers\API\ScrapeController;
use App\Http\Controllers\API\TurkeyController;
use App\Http\Controllers\API\QozoqController;
use App\Http\Controllers\API\ZiticController;
use App\Http\Controllers\API\ZanjeerApiController;
use App\Http\Controllers\API\AutoController;
use App\Http\Controllers\API\TestController;
use App\Http\Controllers\API\OrginfoController;
use App\Http\Controllers\API\ZanjeerUploadFilesController;
use App\Http\Controllers\API\ConvertEomborFilesController;
use App\Http\Controllers\API\MergeExcelFilesController;
use App\Http\Controllers\API\ConvertTurkiyaFilesController;
use App\Http\Controllers\API\SearchUzbekCarNumberController;
use App\Http\Controllers\CheckpointScraperController;


// Telegram serverlari chaqiradi — login talab qilinmaydi
Route::post('/telegram/webhook', [TelegramBotController::class, 'webhook']);

// Qolgan barcha endpoint'lar faqat brauzerdagi sahifalardan chaqiriladi:
// 'web' guruhi session cookie + CSRF tekshiruvini beradi, 'auth' esa login talab qiladi.
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    })->middleware('auth:sanctum');

    Route::get('/test', function () {
        return 'API OK';
    });

    Route::post('/checkpoints/scrape-all', [CheckpointScraperController::class, 'scrapeAll'])->middleware('permission:operation.scraping');



    Route::post('/scrape/declarant', [ScrapeController::class, 'scrape'])->name('scrape')->middleware('permission:page.belarus');
    Route::get('/scrape/files', [ScrapeController::class, 'files'])->middleware('permission:page.belarus|page.declarant-converter|page.zanjeer');
    Route::get('/scrape/download', [ScrapeController::class, 'download'])->middleware('permission:page.belarus|page.declarant-converter|page.zanjeer');

    Route::post('/scrape/{zone}',[ScrapeController::class, 'scrape'])->middleware('permission:page.belarus');
    Route::get('/scrape/files', [ScrapeController::class, 'files'])->middleware('permission:page.belarus|page.declarant-converter|page.zanjeer');
    Route::get('/scrape/download', [ScrapeController::class, 'download'])->middleware('permission:page.belarus|page.declarant-converter|page.zanjeer');
    Route::post('/declarant/convert', [ScrapeController::class, 'convert'])->middleware('permission:page.declarant-converter');
    Route::get('/scrape/download/{file}', [ScrapeController::class, 'downloadFile'])->middleware('permission:page.belarus|page.declarant-converter|page.zanjeer');

    Route::post('/turkey/scrape', [TurkeyController::class, 'scrape'])->name('turkey.scrape')->middleware('permission:page.turkey');
    Route::get('/turkey/status', [TurkeyController::class, 'checkScrapingStatus'])->name('turkey.status')->middleware('permission:page.turkey');
    Route::get('/turkey/files', [TurkeyController::class, 'listFiles'])->name('turkey.files')->middleware('permission:page.turkey|page.turkiya-converter|page.zanjeer');
    Route::get('/turkey/download/{file}', [TurkeyController::class, 'downloadFile'])->name('turkey.download')->middleware('permission:page.turkey|page.turkiya-converter|page.zanjeer');

    Route::get('/qozoq/scrape/{checkpoint}', [QozoqController::class, 'scrape'])->middleware('permission:page.qozoq');
    Route::get('/qozoq/files', [QozoqController::class, 'getFiles'])->middleware('permission:page.qozoq');
    Route::get('/qozoq/download/{file}', [QozoqController::class, 'download'])->middleware('permission:page.qozoq');

    Route::post('/upload-qozoq', [QozoqController::class, 'upload'])->middleware('permission:page.check-qozoq');
    Route::get('/qozoq/import/files', [QozoqController::class, 'getFilesImport'])->middleware('permission:page.check-qozoq|page.zanjeer|page.upload-zanjeer');
    Route::get('/qozoq/import/download/{file}', [QozoqController::class, 'downloadImportFile'])->middleware('permission:page.check-qozoq|page.zanjeer|page.upload-zanjeer');

    Route::post('/scrape-zitic', [ZiticController::class, 'scrape'])->middleware('permission:page.zitic');
    Route::get('/zitic/files', [ZiticController::class, 'listFiles'])->middleware('permission:page.zitic|page.zitic-converter|page.zanjeer');
    Route::get('/zitic/download/{file}', [ZiticController::class, 'download'])->middleware('permission:page.zitic|page.zitic-converter|page.zanjeer');
    Route::post('/zitic/convert', [ZiticController::class, 'convert'])->middleware('permission:page.zitic-converter');

    Route::post('/orginfo/fill-inn', [OrginfoController::class, 'fillInn'])->middleware('permission:page.orginfo');
    Route::get('/orginfo/check/{file}',[OrginfoController::class, 'checkFile'])->middleware('permission:page.orginfo');
    Route::get('/orginfo/download/{file}',[OrginfoController::class, 'download'])->middleware('permission:page.orginfo');
    Route::get('/orginfo/files', [OrginfoController::class, 'listFiles'])->middleware('permission:page.orginfo');
    Route::get('/orginfo/file-download/{file}', [OrginfoController::class, 'downloadFile'])->middleware('permission:page.orginfo');

    Route::post('/zanjeer/scrape', [ZanjeerApiController::class, 'scrape'])->middleware('permission:page.zanjeer');
    Route::get('/zanjeer/files', [ZanjeerApiController::class, 'listFiles'])->middleware('permission:page.zanjeer|page.orginfo');
    Route::get('/zanjeer/download/{file}', [ZanjeerApiController::class, 'download'])->middleware('permission:page.zanjeer');
    Route::get('/zanjeer/check',[ZanjeerApiController::class, 'check'])->middleware('permission:page.zanjeer');

    Route::post('/normalize-excel', [ZanjeerApiController::class, 'normalize'])->middleware('permission:page.zanjeer');
    Route::get('/zanjeer/merge-files', [ZanjeerApiController::class, 'listMergeFiles'])->middleware('permission:page.zanjeer');
    Route::get('/normalize/download/{file}',[ZanjeerApiController::class, 'downloadFile'])->middleware('permission:page.zanjeer');

    Route::get('/autos', [AutoController::class, 'index'])->middleware('permission:page.autos');

    Route::post('/zanjeer-upload-files', [ZanjeerUploadFilesController::class, 'downloadBorderZip'])->middleware('permission:page.upload-zanjeer');

    Route::post('/convert-e-ombor-excel', [ConvertEomborFilesController::class, 'convert'])->middleware('permission:page.eombor-converter');

    Route::post('/merge-excel-files', [MergeExcelFilesController::class, 'merge'])->middleware('permission:page.merge-excel');

    Route::post('/border-convert', [ConvertTurkiyaFilesController::class, 'convert'])->middleware('permission:page.turkiya-converter');

    Route::post('/filter-uz-plates', [SearchUzbekCarNumberController::class, 'filterUzPlates'])->middleware('permission:page.uzbek-plates');

    Route::get('/test', [TestController::class, 'index']);
});
