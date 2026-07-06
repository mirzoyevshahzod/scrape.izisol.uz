<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\FillInnJob;
use Illuminate\Http\Request;

class OrginfoController extends Controller
{
    public function fillInn(Request $request)
    {
        $request->validate([
            'file' => 'required|string',
        ]);

        $fileName = basename($request->file);

        $fullInputPath =
            storage_path('app/public/' . $fileName);

        if (!file_exists($fullInputPath)) {

            return response()->json([
                'success' => false,
                'message' => 'File topilmadi'
            ], 404);
        }

        $outputDir = storage_path('app/orginfo');

        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0775, true);
        }

        $outputFileName =
            'malumotlar_' .
            now()->format('Y_m_d_H_i') .
            '.xlsx';

        $outputPath =
            $outputDir . '/' . $outputFileName;

        FillInnJob::dispatch(
            $fullInputPath,
            $outputPath
        );

        return response()->json([
            'success' => true,
            'message' => 'Job queue ga tushdi',
            'file_name' => $outputFileName,
        ]);
    }


    public function checkFile($file)
    {
        $path =
            storage_path('app/orginfo/' . $file);

        if (!file_exists($path)) {

            return response()->json([
                'ready' => false
            ]);
        }

        return response()->json([
            'ready' => true,
            'download_url' =>
                url('/api/orginfo/download/' . $file)
        ]);
    }
    

    public function download($file)
    {
        $path =
            storage_path('app/orginfo/' . $file);

        if (!file_exists($path)) {
            abort(404);
        }

        return response()->download($path);
    }

    public function listFiles()
    {
        $files = glob(storage_path('app/orginfo/*'));

        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $fileList = array_map(function ($file) {
            return basename($file);
        }, $files);

        return response()->json([
            'status' => true,
            'files' => $fileList
        ]);
    }

    public function downloadFile($file)
    {
        $path =
            storage_path('app/orginfo/' . $file);

        if (!file_exists($path)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found',
            ], 404);
        }
        return response()->download($path);
    }

 
}