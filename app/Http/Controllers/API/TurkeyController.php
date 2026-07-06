<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TurkeyController extends Controller
{
    /**
     * 🔹 HTML Scraper ishga tushirish
     */
    public function scrape()
    {
        try {

            Artisan::call('scrape:html');

            $output = Artisan::output();

            preg_match('/Excel saved:\s(.+)/', $output, $matches);

            if (!isset($matches[1])) {

                return response()->json([
                    'status' => false,
                    'message' => 'Excel fayl topilmadi'
                ], 500);
            }

            $filePath = trim($matches[1]);

            if (!file_exists($filePath)) {

                return response()->json([
                    'status' => false,
                    'message' => 'Excel mavjud emas'
                ], 500);
            }

            return response()->download(
                $filePath,
                basename($filePath),
                [
                    'Content-Type' =>
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]
            );

        } catch (\Exception $e) {

            \Log::error('Turkey scrape error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status'  => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🔹 Scraping holatini tekshirish
     */
    public function checkScrapingStatus()
    {
        $files = glob(storage_path('app/turkey/turkey_scrape-*.xlsx'));

        if (empty($files)) {
            return response()->json([
                'status' => 'processing',
                'file_exists' => false
            ]);
        }

        // eng oxirgi fayl
        usort($files, fn ($a, $b) => filemtime($b) - filemtime($a));
        $latest = $files[0];

        return response()->json([
            'status'       => 'completed',
            'file_exists'  => true,
            'file_name'    => basename($latest),
            'file_size'    => filesize($latest),
            'file_time'    => date('Y-m-d H:i:s', filemtime($latest)),
            'download_url' => route('turkey.download', ['file' => basename($latest)])
        ]);
    }

    /**
     * 🔹 Fayl download
     */
    public function download(string $file)
    {
        $path = storage_path('app/turkey/' . basename($file));

        if (!file_exists($path)) {
            return response()->json([
                'status'  => false,
                'message' => 'Fayl topilmadi'
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->download(
            $path,
            $file,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]
        );
    }

     public function downloadFile(string $file)
    {
        $file = basename($file); // 🔒 XAVFSIZLIK
        $path = storage_path('app/turkey/' . $file);

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



    /**
     * 🔹 Barcha scraping fayllar ro‘yxati
     */
    public function listFiles()
    {
        $files = glob(storage_path('app/turkey/turkey_scrape-*.xlsx'));
        usort($files, fn ($a, $b) => filemtime($b) - filemtime($a));

        return response()->json([
            'status' => true,
            'files'  => array_map('basename', $files)
        ]);
    }
}
