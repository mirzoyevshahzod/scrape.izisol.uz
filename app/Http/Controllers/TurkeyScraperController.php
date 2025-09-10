<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class TurkeyScraperController extends Controller
{
    public function index()
    {
        set_time_limit(0);
        Log::info('Turkey Data Scraper sahifasi yuklandi');
        return view('turkey');
    }

    public function scrape(Request $request)
    {
        set_time_limit(0);
        Log::info('CONTROLLER: Scrape methodi boshlandi');

        try {
            // Fayl yo'li
            $fileName = 'uzbek_plates.xlsx';
            $filePath = storage_path("app/{$fileName}");
            Log::info('CONTROLLER: Fayl yo\'li belgilandi', [
                'fileName' => $fileName,
                'filePath' => $filePath
            ]);

            // Storage papkasini tekshirish
            $storageDir = storage_path('app');
            if (!is_dir($storageDir)) {
                Log::error('CONTROLLER: Storage papkasi mavjud emas', ['path' => $storageDir]);
                return response()->json(['error' => 'Storage papkasi mavjud emas'], 500);
            }

            if (!is_writable($storageDir)) {
                Log::error('CONTROLLER: Storage papkasiga yozish huquqi yo\'q', ['path' => $storageDir]);
                return response()->json(['error' => 'Storage papkasiga yozish huquqi yo\'q'], 500);
            }

            Log::info('CONTROLLER: Storage papkasi tekshirildi - OK');

            // Artisan command chaqirish
            Log::info('CONTROLLER: Artisan command chaqirilmoqda', ['command' => 'scrape']);
            $exitCode = Artisan::call('scrape');
            $artisanOutput = Artisan::output();
            Log::info('CONTROLLER: Artisan command tugadi', [
                'exit_code' => $exitCode,
                'output' => $artisanOutput
            ]);

            // Fayl mavjudligini tekshirish
            Log::info('CONTROLLER: Fayl mavjudligi tekshirilmoqda', ['file_path' => $filePath]);
            if (!file_exists($filePath)) {
                Log::error('CONTROLLER: Fayl mavjud emas', ['file_path' => $filePath]);
                $files = scandir(storage_path('app'));
                Log::info('CONTROLLER: Storage dagi barcha fayllar', ['files' => $files]);
                return response()->json(['error' => 'Excel fayli yaratilmadi. Selenium server ishlamasligini tekshiring.'], 500);
            }

            $fileSize = filesize($filePath);
            Log::info('CONTROLLER: Fayl topildi', [
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'file_size_mb' => round($fileSize / 1024 / 1024, 2)
            ]);

            if ($fileSize <= 6232) {
                Log::warning('CONTROLLER: Fayl bo\'sh yoki faqat minimal ma\'lumot (bitta yozuv) mavjud. Pagination muammosi bo\'lishi mumkin.', [
                    'file_path' => $filePath,
                    'file_size' => $fileSize
                ]);
                return response()->json(['warning' => 'Excel faylida faqat bitta yozuv yoki sarlavhalar mavjud. Barcha sahifalar o\'qilmagan bo\'lishi mumkin.'], 200);
            }

            // Download uchun tayyorlash
            Log::info('CONTROLLER: Fayl download uchun tayyorlanmoqda');
            return response()->download($filePath, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(false);

        } catch (\Exception $e) {
            Log::error('CONTROLLER: Umumiy xatolik', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Xatolik yuz berdi: ' . $e->getMessage()], 500);
        }
    }
}