<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use App\Models\VehicleData;

class ScrapeController extends Controller
{
    public function index(Request $request)
    {
        try {
            Log::info('ScrapeController: Belarus index sahifasi ochildi');
            
            $query = VehicleData::query();

            // Filtrlarni qo'llash
            if ($request->search) {
                $query->where('reg_number', 'like', '%' . $request->search . '%');
            }
            if ($request->region_filter) {
                $query->where('region', $request->region_filter);
            }
            
            // Pagination bilan ma'lumotlarni olish
            $allData = $query->orderBy('created_at', 'DESC')->paginate(10);

            return view('belarus', compact('allData'));
        } catch (\Exception $e) {
            Log::error('ScrapeController: Index sahifasini ochishda xato: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Sahifani ochishda xato yuz berdi.');
        }
    }

    public function scrape(Request $request)
    {
        set_time_limit(0); // Uzun jarayonlar uchun
        try {
            Log::info('ScrapeController: Scrape so‘rovi boshlandi: ' . json_encode($request->all()));

            // Validatsiya
            $validated = $request->validate([
                'region' => 'required|in:benyakoni,brest,grigorovschina,kamennyy_log,kozlovichi',
            ]);

            $region = $validated['region'];
            $regionData = $this->getRegionData($region);
            $url = $regionData['url'];
            $name = $regionData['name'];

            // Fayl nomini yaratish
            $fileName = "declarant-{$name}-" . date('Y-m-d-H-i-s') . ".xlsx";
            $filePath = storage_path("app/{$fileName}");
            Log::info('ScrapeController: Fayl yo\'li belgilandi', [
                'fileName' => $fileName,
                'filePath' => $filePath,
                'regionUrl' => $url,
                'regionName' => $name
            ]);

            // Storage papkasini tekshirish
            $storageDir = storage_path('app');
            if (!is_dir($storageDir)) {
                Log::error('ScrapeController: Storage papkasi mavjud emas', ['path' => $storageDir]);
                return redirect()->back()->with('error', 'Storage papkasi mavjud emas');
            }
            
            if (!is_writable($storageDir)) {
                Log::error('ScrapeController: Storage papkasiga yozish huquqi yo\'q', ['path' => $storageDir]);
                return redirect()->back()->with('error', 'Storage papkasiga yozish huquqi yo\'q');
            }

            // scrape:declarant commandini ishga tushirish
            Artisan::call('scrape:declarant', [
                'region' => $region,
                '--url' => $url,
                '--name' => $name
            ]);
            Log::info('ScrapeController: scrape:declarant commandi ishga tushirildi', ['output' => Artisan::output()]);

            // Eng yangi faylni topish
            $foundFile = $this->findLatestFile($name);
            if (!$foundFile || !file_exists($foundFile)) {
                Log::error('ScrapeController: Fayl mavjud emas', ['expected_path' => $filePath, 'found_file' => $foundFile]);
                return redirect()->back()->with('error', 'Excel fayli yaratilmadi. Selenium server yoki internet aloqasini tekshiring.');
            }

            $fileSize = filesize($foundFile);
            Log::info('ScrapeController: Fayl topildi', [
                'file_path' => $foundFile,
                'file_size' => $fileSize,
                'file_size_mb' => round($fileSize / 1024 / 1024, 2)
            ]);

            if ($fileSize <= 1000) { // 1KB dan kichik bo'lsa bo'sh deb hisoblaymiz
                Log::error('ScrapeController: Fayl juda kichik yoki bo\'sh', [
                    'file_path' => $foundFile,
                    'file_size' => $fileSize
                ]);
                return redirect()->back()->with('error', 'Excel fayli bo\'sh yaratildi. O\'zbek mashina raqamlari topilmagan bo\'lishi mumkin.');
            }

            // Excel faylni avtomatik yuklash
            return response()->download($foundFile, basename($foundFile), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(false);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('ScrapeController: Validation xatoligi', [
                'errors' => $e->errors(),
                'message' => $e->getMessage()
            ]);
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('ScrapeController: Scrape xatoligi: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Xato yuz berdi: ' . $e->getMessage());
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