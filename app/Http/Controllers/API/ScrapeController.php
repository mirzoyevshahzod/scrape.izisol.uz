<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ScrapeController extends Controller
{
    public function scrape($zone)
    {
        Artisan::call(
            'scrape:declarant-data',
            [
                'zone' => $zone
            ]
        );

        $output = Artisan::output();

        \Log::info($output);

        preg_match(
            '/Excel saved:\s(.+)/',
            $output,
            $matches
        );

        if (!isset($matches[1])) {

            return response()->json([
                'success' => false,
                'message' => 'Excel file not generated',
                'output'  => $output
            ], 500);
        }

        $path = trim($matches[1]);

        if (!file_exists($path)) {

            return response()->json([
                'success' => false,
                'message' => 'File not found',
                'path'    => $path
            ], 404);
        }

        return response()->download(
            $path,
            basename($path),
            [
                'Content-Type' =>
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]
        );
    }

      public function convert(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $uploadedFile = $request->file('excel_file');

        $inputPath = $uploadedFile->getRealPath();

        // Foydalanuvchi yuklagan fayl nomi
        $originalName = pathinfo(
            $uploadedFile->getClientOriginalName(),
            PATHINFO_FILENAME
        );

        $downloadName = $originalName . '-converted.xlsx';

        $outputPath = storage_path('app/' . $downloadName);

        Artisan::call('declarant:convert', [
            'input'  => $inputPath,
            'output' => $outputPath,
        ]);

        return response()->download($outputPath, $downloadName)
            ->deleteFileAfterSend(true);
    }

    public function files()
    {
        $files = glob(
            storage_path('app/declarant/*.xlsx')
        );

        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        return response()->json([
            'success' => true,
            'files'   => array_map(
                'basename',
                $files
            ),
        ]);
    }
    
    public function download(Request $request)
    {
        $request->validate([
            'file' => 'required|string',
        ]);

        $path = storage_path('app/declarant/' . $request->file);

        if (!file_exists($path)) {
            abort(404, 'Fayl topilmadi');
        }

        return response()->download(
            $path,
            $request->file,
            [
                'Content-Type' =>
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]
        );
    }


     public function downloadFile(string $file)
     {
         $file = basename($file); // 🔒 XAVFSIZLIK
         $path = storage_path('app/declarant/' . $file);

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
    
}