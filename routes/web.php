<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MintransController;
use App\Http\Controllers\OperationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ScrapeController;
use App\Http\Controllers\TurkeyScraperController;
use App\Http\Controllers\API\QozoqController;
use App\Http\Controllers\ZiticController;
use App\Http\Controllers\ZanjeerController;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:5,1')->name('login.attempt');
});

Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    // Default index route
    Route::get('/', DashboardController::class)->name('home');

    // Mintrans Avtoraqam: Excel -> Mintrans API -> natija Excel (queue orqali)
    Route::prefix('mintrans-auto')->name('mintrans.')->middleware('permission:page.mintrans-auto')->group(function () {
        Route::get('/', [MintransController::class, 'index'])->name('index');
        Route::post('/start', [MintransController::class, 'start'])->name('start');
        Route::get('/runs', [MintransController::class, 'runs'])->name('runs');
        Route::get('/runs/{run}', [MintransController::class, 'show'])->name('show');
        Route::get('/runs/{run}/download', [MintransController::class, 'download'])->name('download');
        Route::get('/ping', [MintransController::class, 'ping'])->name('ping');
        Route::get('/missing', [MintransController::class, 'missing'])->name('missing');
        Route::get('/missing/export', [MintransController::class, 'exportMissing'])->name('missing.export');
    });
    //Belarus scraping
    Route::get('/belarus', [HomeController::class, 'belarus'])->name('scrape.data')->middleware('permission:page.belarus');
    //Qozoq scraping
    Route::get('/qozoq', [QozoqController::class, 'index'])->name('qozoq.index')->middleware('permission:page.qozoq');
    Route::get('/check-qozoq', [HomeController::class, 'checkQozoq'])->name('check.qozoq')->middleware('permission:page.check-qozoq');
    //Zitic scraping
    Route::get('/zitic', [HomeController::class, 'zitic'])->name('zitic.index')->middleware('permission:page.zitic');

    //Orginfo scraping
    Route::get('/orginfo', [HomeController::class, 'orginfo'])->name('orginfo.index')->middleware('permission:page.orginfo');

    //Zanjeer scraping
    Route::get('/zanjeer', [HomeController::class, 'scrapeZanjeer'])->name('zanjeer.index')->middleware('permission:page.zanjeer');
    // turkey scraper routes
    Route::get('/turkey', [HomeController::class, 'turkey'])->name('turkey.index')->middleware('permission:page.turkey');
    Route::get('/autos', [HomeController::class, 'autos'])->name('autos.index')->middleware('permission:page.autos');

    Route::get('/upload-zanjeer-file', [HomeController::class, 'uploadZanjeer'])->name('upload-zanjeer')->middleware('permission:page.upload-zanjeer');

    Route::get('/e-ombor-converter', [HomeController::class, 'eOmborConverter'])->name('eOmborConverter')->middleware('permission:page.eombor-converter');

    Route::get('/turkiya-converter', [HomeController::class, 'turkiyaConverter'])->name('turkiyaConverter')->middleware('permission:page.turkiya-converter');

    Route::get('/zitic-converter', [HomeController::class, 'ziticConverter'])->name('ziticConverter')->middleware('permission:page.zitic-converter');

    Route::get('/declarant-converter', [HomeController::class, 'declarantConverter'])->name('declarantConverter')->middleware('permission:page.declarant-converter');

    Route::get('/filter-uzbek-car-number', [HomeController::class, 'filterUzbekCarNumber'])->name('filterUzbekCarNumber')->middleware('permission:page.uzbek-plates');

    Route::get('/merge-excel-files', [HomeController::class, 'mergeExcelFiles'])->name('mergeExcelFiles')->middleware('permission:page.merge-excel');

    // Boshqaruv: queue orqali amallarni ishga tushirish (har bir amal o'z permission'ini controller'da tekshiradi)
    Route::prefix('operations')->name('operations.')
        ->middleware('permission:operation.crm-login|operation.pipeline|operation.telegram|operation.crm-import|operation.scraping')
        ->group(function () {
            Route::get('/', [OperationController::class, 'index'])->name('index');
            Route::get('/runs', [OperationController::class, 'runs'])->name('runs');
            Route::get('/runs/{run}', [OperationController::class, 'show'])->name('show');
            Route::post('/{operation}/run', [OperationController::class, 'run'])->name('run');
        });

    // Administrator: foydalanuvchilar va rollar
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UserController::class)->except('show')->middleware('permission:admin.users');
        Route::resource('roles', RoleController::class)->except('show')->middleware('permission:admin.roles');
    });
});
