<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramBotController;
use App\Http\Controllers\API\ExampleController;
use App\Http\Controllers\API\MintransController;
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
use App\Http\Controllers\API\ConvertTurkiyaFilesController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('/telegram/webhook', [TelegramBotController::class, 'webhook']);

Route::get('/test', function () {
    return 'API OK';
});

Route::get('/mintrans/all-files', [ExampleController::class, 'listIntegratedFiles']);
Route::post('/mintrans/download', [ExampleController::class, 'FileDownload']);
Route::post('/mintrans/submit',[ExampleController::class,'submit']);
Route::get('/mintrans/check/{jobId}',[ExampleController::class,'check']);
Route::get('/mintrans/download/{jobId}',[ExampleController::class,'download']);

Route::post('/upload', [MintransController::class, 'upload'])->name('upload.store');
Route::get('/scrape/check-result/{jobId}', [MintransController::class, 'checkStatus']);
Route::get('/download/{jobId}', [MintransController::class, 'download']);
Route::get('/scrape/all-files', [MintransController::class, 'listIntegratedFiles']);
Route::post('/scrape/download', [MintransController::class, 'FileDownload']);



Route::post('/scrape/declarant', [ScrapeController::class, 'scrape'])->name('scrape');
Route::get('/scrape/files', [ScrapeController::class, 'files']);
Route::get('/scrape/download', [ScrapeController::class, 'download']);

Route::post('/belarus/scrape/download', [ScrapeController::class, 'FileDownload']);
Route::get('/belarus/details/{id}', [ScrapeController::class, 'getBelarusDetails']);
Route::get('/scrape/check', [ScrapeController::class, 'check']);
Route::get('/docs/checkpoint', [ScrapeController::class, 'checkpointDocsDocx']);

Route::post('/scrape/{zone}',[ScrapeController::class, 'scrape']);
Route::get('/scrape/files', [ScrapeController::class, 'files']);
Route::get('/scrape/download', [ScrapeController::class, 'download']);
Route::post('/declarant/convert', [ScrapeController::class, 'convert']);
Route::get('/scrape/download/{file}', [ScrapeController::class, 'downloadFile']);

Route::post('/turkey/scrape', [TurkeyController::class, 'scrape'])->name('turkey.scrape');
Route::get('/turkey/status', [TurkeyController::class, 'checkScrapingStatus'])->name('turkey.status');
Route::get('/turkey/files', [TurkeyController::class, 'listFiles'])->name('turkey.files');
Route::get('/turkey/download/{file}', [TurkeyController::class, 'downloadFile'])->name('turkey.download');

Route::get('/qozoq/scrape/{checkpoint}', [QozoqController::class, 'scrape']);
Route::get('/qozoq/files', [QozoqController::class, 'getFiles']);
Route::get('/qozoq/download/{file}', [QozoqController::class, 'download']);

Route::post('/upload-qozoq', [QozoqController::class, 'upload']);
Route::get('/qozoq/import/files', [QozoqController::class, 'getFilesImport']);
Route::get('/qozoq/import/download/{file}', [QozoqController::class, 'downloadImportFile']);

Route::post('/scrape-zitic', [ZiticController::class, 'scrape']);
Route::get('/zitic/files', [ZiticController::class, 'listFiles']);
Route::get('/zitic/download/{file}', [ZiticController::class, 'download']);
Route::post('/zitic/convert', [ZiticController::class, 'convert']);

Route::post('/orginfo/fill-inn', [OrginfoController::class, 'fillInn']);
Route::get('/orginfo/check/{file}',[OrginfoController::class, 'checkFile']);
Route::get('/orginfo/download/{file}',[OrginfoController::class, 'download']);
Route::get('/orginfo/files', [OrginfoController::class, 'listFiles']);
Route::get('/orginfo/file-download/{file}', [OrginfoController::class, 'downloadFile']);

Route::post('/zanjeer/scrape', [ZanjeerApiController::class, 'scrape']);
Route::get('/zanjeer/files', [ZanjeerApiController::class, 'listFiles']);
Route::get('/zanjeer/download/{file}', [ZanjeerApiController::class, 'download']);
Route::get('/zanjeer/check',[ZanjeerApiController::class, 'check']);

Route::post('/normalize-excel', [ZanjeerApiController::class, 'normalize']);
Route::get('/zanjeer/merge-files', [ZanjeerApiController::class, 'listMergeFiles']);
Route::get('/normalize/download/{file}',[ZanjeerApiController::class, 'downloadFile']);

Route::get('/autos', [AutoController::class, 'index']);

Route::post('/zanjeer-upload-files', [ZanjeerUploadFilesController::class, 'downloadBorderZip']);

Route::post('/convert-e-ombor-excel', [ConvertEomborFilesController::class, 'convert']);

Route::post('/border-convert', [ConvertTurkiyaFilesController::class, 'convert']);

Route::get('/test', [TestController::class, 'index']);