<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use App\Models\TurkeyData;

class TurkeyScraperController extends Controller
{
    public function index(Request $request)
    {
        set_time_limit(0);
        Log::info('TurkeyScraperController: index sahifasi yuklandi', [
            'request_params' => $request->all()
        ]);

        try {
            // Ma'lumotlarni olish va filtrlar
            $query = TurkeyData::query();

            // Search bo'yicha filtr (mashina raqami)
            if ($request->has('search') && !empty($request->search)) {
                $query->where('plaka', 'like', '%' . $request->search . '%');
                Log::info('TurkeyScraperController: Search filtri qo\'llanildi', ['search' => $request->search]);
            }

            // Region (yer) bo'yicha filtr
            if ($request->has('region_filter') && !empty($request->region_filter)) {
                $query->where('yer', $request->region_filter);
                Log::info('TurkeyScraperController: Region filtri qo\'llanildi', ['region_filter' => $request->region_filter]);
            }

            // Ma'lumotlarni pagination bilan olish (har sahifada 6 qator)
            $allData = $query->orderBy('created_at', 'DESC')->paginate(10);
            Log::info('TurkeyScraperController: Ma\'lumotlar o\'qildi', [
                'total_records' => $allData->total(),
                'current_page' => $allData->currentPage()
            ]);

            return view('turkey', compact('allData'));
        } catch (\Exception $e) {
            Log::error('TurkeyScraperController: index xatoligi', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return redirect()->back()->with('error', 'Sahifani ochishda xato yuz berdi: ' . $e->getMessage());
        }
    }

    public function scrape(Request $request)
    {
        set_time_limit(0);
        Log::info('TurkeyScraperController: Scrape methodi boshlandi', [
            'request_params' => $request->all()
        ]);

        try {
            // Storage papkasini tekshirish
            $storageDir = storage_path('app/public');
            if (!is_dir($storageDir)) {
                Log::error('TurkeyScraperController: Storage papkasi mavjud emas', ['path' => $storageDir]);
                return redirect()->back()->with('error', 'Storage papkasi mavjud emas');
            }

            if (!is_writable($storageDir)) {
                Log::error('TurkeyScraperController: Storage papkasiga yozish huquqi yo\'q', ['path' => $storageDir]);
                return redirect()->back()->with('error', 'Storage papkasiga yozish huquqi yo\'q');
            }

            Log::info('TurkeyScraperController: Storage papkasi tekshirildi - OK');

            // Artisan command chaqirish
            Log::info('TurkeyScraperController: Artisan command chaqirilmoqda', ['command' => 'scrape']);
            $exitCode = Artisan::call('scrape');
            $artisanOutput = Artisan::output();
            Log::info('TurkeyScraperController: Artisan command tugadi', [
                'exit_code' => $exitCode,
                'output' => $artisanOutput
            ]);

            // Agar command muvaffaqiyatsiz tugagan bo'lsa
            if ($exitCode !== 0) {
                Log::error('TurkeyScraperController: Artisan command xato bilan tugadi', [
                    'exit_code' => $exitCode,
                    'output' => $artisanOutput
                ]);
                return redirect()->back()->with('error', 'Scraping jarayonida xato yuz berdi. Selenium server ishlamasligini tekshiring.');
            }

            // Cache dan fayl yo'lini olish
            $cachedFilePath = cache('excel_file_path');
            if (!$cachedFilePath || !file_exists($cachedFilePath)) {
                // Agar cache da yo'q bo'lsa, oxirgi yaratilgan faylni topishga harakat qilamiz
                $files = glob(storage_path('app/public/turkiya-gruziya-*.xlsx'));
                if (!empty($files)) {
                    // Eng oxirgisini olish (vaqt bo'yicha)
                    usort($files, function($a, $b) {
                        return filemtime($b) - filemtime($a);
                    });
                    $cachedFilePath = $files[0];
                    Log::info('TurkeyScraperController: Fayl glob orqali topildi', ['file_path' => $cachedFilePath]);
                } else {
                    Log::error('TurkeyScraperController: Hech qanday Excel fayl topilmadi');
                    return redirect()->back()->with('error', 'Excel fayli yaratilmadi yoki topilmadi.');
                }
            }

            // Fayl mavjudligini tekshirish
            if (!file_exists($cachedFilePath)) {
                Log::error('TurkeyScraperController: Fayl mavjud emas', ['file_path' => $cachedFilePath]);
                $files = scandir($storageDir);
                Log::info('TurkeyScraperController: Storage dagi barcha fayllar', ['files' => $files]);
                return redirect()->back()->with('error', 'Excel fayli topilmadi.');
            }

            $fileSize = filesize($cachedFilePath);
            Log::info('TurkeyScraperController: Fayl topildi', [
                'file_path' => $cachedFilePath,
                'file_size' => $fileSize,
                'file_size_mb' => round($fileSize / 1024 / 1024, 2)
            ]);

            // Fayl hajmini tekshirish (faqat sarlavhalar bor yoki yo'q)
            if ($fileSize <= 6232) {
                Log::warning('TurkeyScraperController: Fayl bo\'sh yoki faqat minimal ma\'lumot mavjud.', [
                    'file_path' => $cachedFilePath,
                    'file_size' => $fileSize
                ]);
                return redirect()->back()->with('warning', 'Excel faylida faqat sarlavhalar mavjud. Ma\'lumotlar topilmadi yoki barcha sahifalar o\'qilmadi.');
            }

            // Fayl nomini olish (path dan)
            $fileName = basename($cachedFilePath);
            
            Log::info('TurkeyScraperController: Fayl download uchun tayyorlanmoqda', [
                'file_name' => $fileName,
                'file_path' => $cachedFilePath
            ]);

            // Cache dan fayl yo'lini o'chirish (bir martalik ishlatish uchun)
            cache()->forget('excel_file_path');

            // Download response qaytarish
            return response()->download($cachedFilePath, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ])->deleteFileAfterSend(true); // Faylni download dan keyin o'chirish

        } catch (\Exception $e) {
            Log::error('TurkeyScraperController: Umumiy xatolik', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Xatolik yuz berdi: ' . $e->getMessage());
        }
    }

    /**
     * Ajax orqali scraping holati tekshirish uchun
     */
    public function checkScrapingStatus()
    {
        try {
            // Oxirgi yaratilgan fayl mavjudligini tekshirish
            $files = glob(storage_path('app/public/turkiya-gruziya-*.xlsx'));
            
            if (!empty($files)) {
                // Eng oxirgisini topish
                usort($files, function($a, $b) {
                    return filemtime($b) - filemtime($a);
                });
                
                $latestFile = $files[0];
                $fileName = basename($latestFile);
                $fileSize = filesize($latestFile);
                $fileTime = filemtime($latestFile);
                
                return response()->json([
                    'status' => 'completed',
                    'file_exists' => true,
                    'file_name' => $fileName,
                    'file_size' => $fileSize,
                    'file_time' => date('Y-m-d H:i:s', $fileTime),
                    'download_url' => route('turkey.download', ['file' => $fileName])
                ]);
            }
            
            return response()->json([
                'status' => 'processing',
                'file_exists' => false
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * To'g'ridan-to'g'ri fayl download qilish
     */
    public function downloadFile($fileName)
    {
        try {
            $filePath = storage_path('app/public/' . $fileName);
            
            if (!file_exists($filePath)) {
                Log::error('TurkeyScraperController: Download uchun fayl topilmadi', ['file_path' => $filePath]);
                return redirect()->back()->with('error', 'Fayl topilmadi.');
            }

            Log::info('TurkeyScraperController: Fayl download qilinmoqda', ['file_name' => $fileName]);

            return response()->download($filePath, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);

        } catch (\Exception $e) {
            Log::error('TurkeyScraperController: Download xatoligi', [
                'message' => $e->getMessage(),
                'file_name' => $fileName
            ]);
            return redirect()->back()->with('error', 'Fayl download qilishda xato: ' . $e->getMessage());
        }
    }

    /**
     * ID bo'yicha ma'lumotlarni modal uchun olish
     */
    public function getDetails($id)
    {
        try {
            $data = TurkeyData::findOrFail($id);
            
            Log::info('TurkeyScraperController: Ma\'lumot tafsilotlari olinmoqda', ['id' => $id]);

            return response()->json([
                'success' => true,
                'data' => [
                    // Asosiy ma'lumotlar
                    'sira' => $data->sira,
                    'giris' => $data->giris,
                    'plaka' => $data->plaka,
                    'tarih' => $data->tarih,
                    'yer' => $data->yer,
                    
                    // Transport ma'lumotlari
                    'rusumi' => $data->rusumi,
                    'yuk_qobiliyati' => $data->yuk_qobiliyati,
                    'transport_turi' => $data->transport_turi,
                    'yuk_turi' => $data->yuk_turi,
                    
                    // Litsenziya ma'lumotlari
                    'license' => $data->license,
                    'state_number' => $data->state_number,
                    'berilgan_sana' => $data->berilgan_sana,
                    'amal_muddati' => $data->amal_muddati,
                    'holati' => $data->holati,
                    'hududiy_boshqarma' => $data->hududiy_boshqarma,
                    
                    // Korxona ma'lumotlari
                    'company' => $data->company,
                    'phone_number' => $data->phone_number,
                    'faoliyat_turi' => $data->faoliyat_turi,
                    
                    // Vaqt belgilari
                    'created_at' => $data->created_at ? $data->created_at->format('d.m.Y H:i') : null,
                    'updated_at' => $data->updated_at ? $data->updated_at->format('d.m.Y H:i') : null,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('TurkeyScraperController: Ma\'lumot tafsilotlari olishda xato', [
                'id' => $id,
                'message' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Ma\'lumot topilmadi yoki xatolik yuz berdi'
            ], 404);
        }
    }
}