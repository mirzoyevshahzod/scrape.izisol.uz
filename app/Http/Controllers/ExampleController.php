<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ExampleController extends Controller
{
    public function index()
    {
        set_time_limit(0);
        Log::info('Mintrans scraping sahifasi yuklandi');
        return view('mintrans_scrape');
    }

    public function submit(Request $request)
    {
        set_time_limit(0);
        Log::info('CONTROLLER: Submit methodi boshlandi');
        Log::info('CONTROLLER: Kelgan ma\'lumotlar', $request->all());

        try {
            // Validation
            Log::info('CONTROLLER: Validation boshlandi');
            $validatedData = $request->validate([
                'id' => 'required|numeric|digits_between:7,10',
                'count' => 'required|integer|min:1',
            ]);
            Log::info('CONTROLLER: Validation muvaffaqiyatli', $validatedData);

            $startId = (int)$request->input('id');
            $count = (int)$request->input('count');
            
            Log::info('CONTROLLER: Start ID va Count', [
                'start_id' => $startId,
                'count' => $count
            ]);
            
            // End ID hisoblash
            $endId = $startId - $count + 1;
            Log::info('CONTROLLER: End ID hisoblandi', [
                'end_id' => $endId,
                'formula' => "{$startId} - {$count} + 1 = {$endId}"
            ]);
            
            // Fayl yo'li
            $fileName = "mintrans-{$startId}-{$endId}.xlsx";
            $filePath = storage_path("app/{$fileName}");
            Log::info('CONTROLLER: Fayl yo\'li belgilandi', [
                'fileName' => $fileName,
                'filePath' => $filePath
            ]);

            // Storage papkasini tekshirish
            $storageDir = storage_path('app');
            if (!is_dir($storageDir)) {
                Log::error('CONTROLLER: Storage papkasi mavjud emas', ['path' => $storageDir]);
                return redirect()->back()->with('error', 'Storage papkasi mavjud emas');
            }
            
            if (!is_writable($storageDir)) {
                Log::error('CONTROLLER: Storage papkasiga yozish huquqi yo\'q', ['path' => $storageDir]);
                return redirect()->back()->with('error', 'Storage papkasiga yozish huquqi yo\'q');
            }
            
            Log::info('CONTROLLER: Storage papkasi tekshirildi - OK');

            // Artisan command chaqirish
            Log::info('CONTROLLER: Artisan command chaqirilmoqda', [
                'command' => 'scrape:mintrans:license',
                'parameters' => [
                    'start_id' => $startId,
                    'count' => $count,
                    'end_id' => $endId
                ]
            ]);

            $exitCode = Artisan::call('scrape:mintrans:license', [
                'start_id' => $startId,
                'count' => $count,
                'end_id' => $endId,
            ]);

            Log::info('CONTROLLER: Artisan command tugadi', [
                'exit_code' => $exitCode,
                'output' => Artisan::output()
            ]);

            // Fayl mavjudligini batafsil tekshirish
            Log::info('CONTROLLER: Fayl mavjudligi tekshirilmoqda', ['file_path' => $filePath]);
            
            if (!file_exists($filePath)) {
                Log::error('CONTROLLER: Fayl mavjud emas', ['file_path' => $filePath]);
                
                // Storage da mavjud fayllarni ko'rish
                $files = scandir(storage_path('app'));
                Log::info('CONTROLLER: Storage dagi barcha fayllar', ['files' => $files]);
                
                return redirect()->back()->with('error', 'Excel fayli yaratilmadi. Selenium server ishlamasligini tekshiring.');
            }

            $fileSize = filesize($filePath);
            Log::info('CONTROLLER: Fayl topildi', [
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'file_size_mb' => round($fileSize / 1024 / 1024, 2)
            ]);

            if ($fileSize <= 0) {
                Log::error('CONTROLLER: Fayl bo\'sh', [
                    'file_path' => $filePath,
                    'file_size' => $fileSize
                ]);
                return redirect()->back()->with('error', 'Excel fayli bo\'sh yaratildi. Ma\'lumot topilmagan bo\'lishi mumkin.');
            }

            // Download uchun tayyorlash
            Log::info('CONTROLLER: Fayl download uchun tayyorlanmoqda');
            
            return response()->download($filePath, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(false);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('CONTROLLER: Validation xatoligi', [
                'errors' => $e->errors(),
                'message' => $e->getMessage()
            ]);
            return redirect()->back()->withErrors($e->errors())->withInput();
            
        } catch (\Exception $e) {
            Log::error('CONTROLLER: Umumiy xatolik', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Xatolik yuz berdi: ' . $e->getMessage());
        }
    }
}