<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessMintransExcel;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;



class MintransController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:2048',
        ]);

        $file = $request->file('excel_file');

        $jobId = uniqid();

        $fileName = "input_$jobId.".$file->getClientOriginalExtension();

        $filePath = $file->storeAs('uploads', $fileName, 'public');

        $fullPath = storage_path('app/public/'.$filePath);

        // background artisan command
        $cmd = "php ".base_path('artisan')." mintrans:parse \"$fullPath\" \"$jobId\" > /dev/null 2>&1 &";

        exec($cmd);

        return response()->json([
            'status' => 'processing',
            'job_id' => $jobId
        ]);
    }

    public function checkStatus($jobId)
    {
        $file = storage_path("app/public/results/result_$jobId.xlsx");

        if (file_exists($file)) {
            return response()->json([
                'status' => 'ready',
                'download_url' => url("/api/download/$jobId")
            ]);
        }

        return response()->json([
            'status' => 'processing'
        ]);
    }

   public function download($jobId)
    {
        $filePath = storage_path("app/public/results/result_$jobId.xlsx");

        if (!file_exists($filePath)) {
            return response()->json([
                'message' => 'File not found'
            ], 404);
        }

        return response()->download($filePath);
    }

    public function listIntegratedFiles()
    {
        $pattern = storage_path('app/public/results/result_*');
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
        $filePath = storage_path('app/public/results/' . $fileName);

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
