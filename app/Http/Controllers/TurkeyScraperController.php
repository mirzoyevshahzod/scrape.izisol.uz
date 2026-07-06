<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use App\Models\TurkeyData;
use App\Jobs\RunTurkeyScrapeJob;

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

    public function downloadFile(string $file)
    {
        $file = basename($file); // 🔒 XAVFSIZLIK
        $path = storage_path('app/' . $file);

        \Log::info('DOWNLOAD TRY', [
            'file' => $file,
            'path' => $path,
            'exists' => file_exists($path)
        ]);

        if (!file_exists($path)) {
            return response()->json([
                'status'  => false,
                'message' => 'Fayl topilmadi',
                'path'    => $path
            ], 404);
        }

        return response()->download($path, $file, [
            'Content-Type' =>
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function show($id)
    {
        $data = TurkeyData::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}