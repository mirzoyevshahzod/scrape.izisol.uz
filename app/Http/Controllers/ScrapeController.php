<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ScrapeController extends Controller
{
    public function index()
    {
        set_time_limit(0);
        Log::info('Scraping sahifasi yuklandi');
        return view('belarus');
    }

    public function scrape(Request $request)
    {
        set_time_limit(0);
        Log::info('CONTROLLER: Scrape methodi boshlandi');
        Log::info('CONTROLLER: Kelgan ma\'lumotlar', $request->all());

        try {
            // Validation
            Log::info('CONTROLLER: Validation boshlandi');
            $validatedData = $request->validate([
                'region' => 'required|string|in:benyakoni,brest,grigorovschina,kamennyy_log,kozlovichi',
            ]);
            Log::info('CONTROLLER: Validation muvaffaqiyatli', $validatedData);

            $region = $request->input('region');
            
            Log::info('CONTROLLER: Tanlangan region', [
                'region' => $region
            ]);
            
            // Region nomi va URL ni olish
            $regionData = $this->getRegionData($region);
            
            // Fayl yo'li
            $fileName = "declarant-{$regionData['name']}-" . date('Y-m-d-H-i-s') . ".xlsx";
            $filePath = storage_path("app/{$fileName}");
            Log::info('CONTROLLER: Fayl yo\'li belgilandi', [
                'fileName' => $fileName,
                'filePath' => $filePath,
                'regionUrl' => $regionData['url'],
                'regionName' => $regionData['name']
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

            // Universal command chaqirish
            Log::info('CONTROLLER: Artisan command chaqirilmoqda', [
                'command' => 'scrape:declarant',
                'parameters' => [
                    'region' => $region,
                    'url' => $regionData['url'],
                    'name' => $regionData['name']
                ]
            ]);

            $exitCode = Artisan::call('scrape:declarant', [
                'region' => $region,
                '--url' => $regionData['url'],
                '--name' => $regionData['name']
            ]);

            Log::info('CONTROLLER: Artisan command tugadi', [
                'exit_code' => $exitCode,
                'output' => Artisan::output()
            ]);

            // Fayl mavjudligini tekshirish
            Log::info('CONTROLLER: Fayl mavjudligi tekshirilmoqda', ['file_path' => $filePath]);
            
            // Dinamik fayl nomini topish
            $foundFile = $this->findLatestFile($regionData['name']);
            
            if (!$foundFile || !file_exists($foundFile)) {
                Log::error('CONTROLLER: Fayl mavjud emas', ['expected_path' => $filePath, 'found_file' => $foundFile]);
                
                // Storage da mavjud fayllarni ko'rish
                $files = scandir(storage_path('app'));
                Log::info('CONTROLLER: Storage dagi barcha fayllar', ['files' => $files]);
                
                return redirect()->back()->with('error', 'Excel fayli yaratilmadi. Selenium server yoki internet aloqasini tekshiring.');
            }

            $fileSize = filesize($foundFile);
            Log::info('CONTROLLER: Fayl topildi', [
                'file_path' => $foundFile,
                'file_size' => $fileSize,
                'file_size_mb' => round($fileSize / 1024 / 1024, 2)
            ]);

            if ($fileSize <= 1000) { // 1KB dan kichik bo'lsa bo'sh deb hisoblaymiz
                Log::error('CONTROLLER: Fayl juda kichik yoki bo\'sh', [
                    'file_path' => $foundFile,
                    'file_size' => $fileSize
                ]);
                return redirect()->back()->with('error', 'Excel fayli bo\'sh yaratildi. O\'zbek mashina raqamlari topilmagan bo\'lishi mumkin.');
            }

            // Download uchun tayyorlash
            Log::info('CONTROLLER: Fayl download uchun tayyorlanmoqda');
            
            return response()->download($foundFile, basename($foundFile), [
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
    
    /**
     * Region ma'lumotlarini qaytarish
     */
    private function getRegionData($region)
    {
        $regions = [
            'benyakoni' => [
                'name' => 'benyakoni',
                'url' => 'https://mon.declarant.by/zone/benyakoni',
                'display_name' => 'Бенякони'
            ],
            'brest' => [
                'name' => 'brest',
                'url' => 'https://mon.declarant.by/zone/brest-bts',
                'display_name' => 'Брест'
            ],
            'grigorovschina' => [
                'name' => 'grigorovschina',
                'url' => 'https://mon.declarant.by/zone/grigorovschina',
                'display_name' => 'Григоровщина'
            ],
            'kamennyy_log' => [
                'name' => 'kamennii-log',
                'url' => 'https://mon.declarant.by/zone/kamennii-log',
                'display_name' => 'Каменный Лог'
            ],
            'kozlovichi' => [
                'name' => 'kozlovichi',
                'url' => 'https://mon.declarant.by/zone/kozlovichi',
                'display_name' => 'Козловичи'
            ]
        ];

        return $regions[$region] ?? $regions['benyakoni'];
    }
    
    /**
     * Eng yangi faylni topish
     */
    private function findLatestFile($regionName)
    {
        $storageDir = storage_path('app');
        $files = glob($storageDir . "/declarant-{$regionName}-*.xlsx");
        
        if (empty($files)) {
            return null;
        }
        
        // Eng yangi faylni qaytarish
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        return $files[0];
    }
}