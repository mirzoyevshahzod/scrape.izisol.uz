<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Jobs\RunMintransScrapeJob;
use Symfony\Component\HttpFoundation\Response;


class ExampleController extends Controller
{
    public function submit(Request $request)
    {
        $startId = $request->id;
        $count = $request->count;
        $endId = $startId - $count + 1;

        $jobId = uniqid();

        $filePath = storage_path("app/public/mintrans-$jobId.xlsx");

        $cmd = "php ".base_path('artisan')." mintrans:scrape $startId $endId \"$filePath\" > /dev/null 2>&1 &";

        exec($cmd);

        return response()->json([
            'status' => 'processing',
            'job_id' => $jobId
        ]);
    }

    public function check($jobId)
    {
        $file = storage_path("app/public/mintrans-$jobId.xlsx");

        if (file_exists($file)) {

            return response()->json([
                'status' => 'ready',
                'download_url' => url("/api/mintrans/download/$jobId")
            ]);
        }

        return response()->json([
            'status' => 'processing'
        ]);
    }



    public function download($jobId)
    {
        $file = storage_path("app/public/mintrans-$jobId.xlsx");

        if (!file_exists($file)) {
            abort(404);
        }

        return response()->download($file);
    }


public function listIntegratedFiles()
{
    $pattern = storage_path('app/public/mintrans-*');
    $files = glob($pattern);

      usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

    // faqat fayl nomlarini olish
    $fileNames = array_map('basename', $files);

    return response()->json([
        'status' => true,
        'files' => $fileNames
    ]);
}

public function FileDownload(Request $request)
    {
        $request->validate([
            'file' => 'required|string'
        ]);

        $fileName = $request->file;

        // Faylning to‘liq yo‘li (storage/app/public ichida)
        $filePath = storage_path('app/' . $fileName);

        if (!file_exists($filePath)) {
            return response()->json([
                'status' => false,
                'message' => 'Fayl topilmadi.'
            ], Response::HTTP_NOT_FOUND);
        }

        // 🔽 Faylni download qilib yuborish
        return response()->download($filePath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }
}
