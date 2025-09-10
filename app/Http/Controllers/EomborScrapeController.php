<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EomborScrapeController extends Controller
{
    public function index()
    {
        set_time_limit(0); // Cheksiz vaqtga ruxsat beradi
        return view('scrape-eombor');
    }

    public function scrape(Request $request)
    {
        // dd($request->all()); // Debug maqsadida barcha so‘rov ma'lumotlarini chiqarish
        set_time_limit(0); // Cheksiz vaqtga ruxsat beradi

        $request->validate([
            'command_type' => 'required|in:scrape_eombor,scrape_mintrans',
            'start_id' => 'required|regex:/^[A-Z]{2}\d{11}$/',
            'count' => 'required|integer|min:1|max:4000',
        ]);
        // dd($request->all()); // Debug maqsadida barcha so‘rov ma'lumotlarini chiqarish
        $startId = $request->input('start_id');
        $count = $request->input('count');
        $endId = $this->generateEndTransitId($startId, $count); // Oxirgi ID hisoblash
        $commandType = $request->input('command_type');
        $filePath = '';
        $fileName = '';

        // Kommandaga qarab logikani tanlash
        if ($commandType === 'scrape_eombor') {
            // dd($startId, $count, $endId); // Debug maqsadida
            Artisan::call('scrape:eombor', [
                'start_id' => $startId,
                'count' => $count,
                'end_id' => $endId,
            ]);
            $filePath = storage_path("app/e-ombor-{$startId}-{$endId}.xlsx");
            $fileName = "e-ombor-{$startId}-{$endId}.xlsx";
        } elseif ($commandType === 'scrape_mintrans') {
            // dd($startId, $count, $endId); // Debug maqsadida
            Artisan::call('scrape:mintrans', [
                'start_id' => $startId,
                'count' => $count,
                'end_id' => $endId,
            ]);
            $filePath = storage_path("app/mintrans-{$startId}-{$endId}.xlsx");
            $fileName = "mintrans-{$startId}-{$endId}.xlsx";
        }

        if (file_exists($filePath) && filesize($filePath) > 0) {
            // dd($filePath, $fileName); // Debug maqsadida
            return response()->download($filePath, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(false); // Avtomatik yuklab olish, faylni o‘chirish o‘chirilgan
        }
        // dd($filePath, $fileName); // Debug maqsadida
        return redirect()->back()->with('error', 'Fayl yaratilmadi yoki bo\'sh.');
    }

    private function generateEndTransitId($startTransitId, $increment)
    {
        set_time_limit(0); // Cheksiz vaqtga ruxsat beradi
        $prefix = substr($startTransitId, 0, 2); // "AT"
        $year = substr($startTransitId, 2, 4);   // "2025"
        $number = (int)substr($startTransitId, 6); // "0346677"

        $newNumber = $number + $increment;
        $newNumberPadded = str_pad($newNumber, 7, '0', STR_PAD_LEFT); // 7 xonali qilish

        return $prefix . $year . $newNumberPadded;
    }
}