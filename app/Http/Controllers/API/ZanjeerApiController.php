<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\ScrapeZanjeerJob;
use Illuminate\Support\Str;

class ZanjeerApiController extends Controller
{
   public function scrape(Request $request)
    {
        $resultFile =
            storage_path('app/last_operator_file.txt');

        // OLD RESULT DELETE
        if (file_exists($resultFile)) {

            unlink($resultFile);
        }

        // VALIDATE
        $request->validate([
            'file' => 'required|string'
        ]);

        // FILE NAME
        $fileName = basename($request->file);

        // FULL PATH
        $fullPath =
            storage_path('app/merge/' . $fileName);

        // FILE EXISTS?
        if (!file_exists($fullPath)) {

            return response()->json([
                'success' => false,
                'message' => 'Fayl topilmadi'
            ], 404);
        }

        // JOB PATH
        $relativePath =
            'merge/' . $fileName;

        // DISPATCH JOB
        ScrapeZanjeerJob::dispatch($relativePath);

        return response()->json([
            'success' => true,
            'message' => 'Scraping boshlandi'
        ]);
    }

        public function download(string $file)
        {
            $file = basename($file);

            $path = storage_path('app/public/' . $file);

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

            return response()->download(
                $path,
                $file,
                [
                    'Content-Type' =>
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]
            );
        }

        public function listFiles()
        {
            $files = glob(storage_path('app/public/operators_*.xlsx'));

            $fileList = array_map(function ($file) {
                return basename($file);
            }, $files);
            usort($fileList, function ($a, $b) use ($files) {
                $pathA = storage_path('app/public/' . $a);
                $pathB = storage_path('app/public/' . $b);
                return filemtime($pathB) - filemtime($pathA);
            });

            return response()->json([
                'status' => true,
                'files'  => $fileList
            ]);

        }

        public function check()
        {
            $resultFile =
                storage_path('app/last_operator_file.txt');

            if (!file_exists($resultFile)) {

                return response()->json([
                    'ready' => false
                ]);
            }

            $excelPath = trim(
                file_get_contents($resultFile)
            );

            if (!file_exists($excelPath)) {

                return response()->json([
                    'ready' => false
                ]);
            }

            return response()->json([
                'ready' => true,
                'file'  => basename($excelPath)
            ]);
        }


    public function normalize(Request $request)
    {
        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'required|file|mimes:xlsx,xls',
        ]);

        $uploadedFiles = [];

        // TEMP PAPKA
        $tempPath = storage_path('app/temp/excels');

        if (!file_exists($tempPath)) {
            mkdir($tempPath, 0777, true);
        }

        // FILELARNI SAVE QILISH
        foreach ($request->file('files') as $file) {

            $fileName =
                Str::random(20) . '.' . $file->getClientOriginalExtension();

            $file->move($tempPath, $fileName);

            $uploadedFiles[] =
                $tempPath . '/' . $fileName;
        }

        // OUTPUT FILE NAME
        $outputFileName =
            'birlashtirilgan_file_' .
            now()->format('Y-m-d-H-i') .
            '.xlsx';

        // COMMAND ISHGA TUSHIRISH
        Artisan::call('excel:normalize', [
            'output' => $outputFileName,
            'files' => $uploadedFiles,
        ]);

        // TEMP FILELARNI O'CHIRISH
        foreach ($uploadedFiles as $file) {

            if (file_exists($file)) {
                unlink($file);
            }
        }

        return response()->json([
            'success' => true,

            // storage/app/merge
            'download_url' =>
                url('/api/normalize/download/' . $outputFileName),

            'file' => $outputFileName,

            'message' =>
                'Excel file normalized successfully'
        ]);
    }

     public function listMergeFiles()
        {
            $files = glob(storage_path('app/merge/*.xlsx'));

            $fileList = array_map(function ($file) {
                return basename($file);
            }, $files);
            usort($fileList, function ($a, $b) use ($files) {
                $pathA = storage_path('app/merge/' . $a);
                $pathB = storage_path('app/merge/' . $b);
                return filemtime($pathB) - filemtime($pathA);
            });

            return response()->json([
                'status' => true,
                'files'  => $fileList
            ]);

        }


    public function downloadFile($file)
    {
        $path =
            storage_path('app/merge/' . $file);

        if (!file_exists($path)) {
            abort(404);
        }

        return response()->download($path);
    }
}