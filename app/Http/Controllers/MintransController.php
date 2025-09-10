<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MintransController extends Controller
{
    public function index()
    {
        try {
            Log::info('MintransController: Index sahifasi ochildi');
            return view('mintrans_auto');
        } catch (\Exception $e) {
            Log::error('MintransController: Index sahifasini ochishda xato: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Sahifani ochishda xato yuz berdi.');
        }
    }

    public function upload(Request $request)
    {
        set_time_limit(0); // Allow long-running processes

        try {
            Log::info('MintransController: Fayl yuklash so‘rovi boshlandi: ' . json_encode($request->all()));

            // Faylni validatsiya qilish
            $request->validate([
                'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:2048',
            ]);
            Log::info('MintransController: Fayl validatsiyadan o‘tdi');

            // Faylni olish va saqlash
            $file = $request->file('excel_file');
            if (!$file || !$file->isValid()) {
                Log::error('MintransController: Fayl yuklanmadi yoki noto‘g‘ri');
                return redirect()->back()->with('error', 'Fayl yuklanmadi yoki noto‘g‘ri.');
            }

            $originalName = $file->getClientOriginalName();
            $fileName = pathinfo($originalName, PATHINFO_FILENAME) . '_' . time() . '.' . $file->getClientOriginalExtension();
            $filePath = 'uploads/' . $fileName;
            $absolutePath = storage_path('app/public/' . $filePath);

            // Faylni saqlash
            if (!$file->move(storage_path('app/public/uploads'), $fileName)) {
                Log::error('MintransController: Fayl saqlanmadi: ' . $absolutePath);
                return redirect()->back()->with('error', 'Fayl saqlanmadi.');
            }
            Log::info('MintransController: Fayl saqlandi: ' . $absolutePath);

            // Command-ni ishga tushirish
            Artisan::call('mintrans:scrape', [
                'filePath' => $absolutePath,
            ]);
            Log::info('MintransController: MintransCommand ishga tushirildi');

            // Natija faylini olish
            $outputFilePath = storage_path('app/public/results/results.xlsx');
            $outputFileName = 'results.xlsx';

            if (file_exists($outputFilePath) && filesize($outputFilePath) > 0) {
                Log::info('MintransController: Natija fayli tayyor: ' . $outputFilePath);
                return response()->download($outputFilePath, $outputFileName, [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])->deleteFileAfterSend(false);
            }

            Log::error('MintransController: Natija fayli yaratilmadi yoki bo‘sh: ' . $outputFilePath);
            return redirect()->back()->with('error', 'Natija fayli yaratilmadi yoki bo‘sh.');

        } catch (\Exception $e) {
            Log::error('MintransController: Xato yuz berdi: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Xato yuz berdi: ' . $e->getMessage());
        } finally {
            // Vaqtincha faylni o‘chirish
            if (isset($filePath) && Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
                Log::info('MintransController: Vaqtincha fayl o‘chirildi: ' . $filePath);
            }
        }
    }
}
