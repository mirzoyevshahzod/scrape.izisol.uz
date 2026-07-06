<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;

class ZiticController extends Controller
{
    public function scrape()
    {
        Artisan::call('scrape:zitic-html');

        $output = Artisan::output();

        preg_match('/FILE_PATH=(.*)/', $output, $matches);

        if (!isset($matches[1])) {

            return response()->json([
                'success' => false,
                'message' => 'Excel topilmadi'
            ], 500);
        }

        $path = trim($matches[1]);

        return response()->download(
            $path,
            basename($path),
            [
                'Content-Type' =>
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ]
        );
    }

    public function listFiles()
    {
        $files = glob(storage_path('app/zitic/zitic_*.xlsx'));
         usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });


        return response()->json([
            'status' => true,
            'files'  => array_map('basename', $files)
        ]);
    }

    public function download(string $file)
    {
        $path = storage_path('app/zitic/' . basename($file));
    
            if (!file_exists($path)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Fayl topilmadi'
                ], 404);
            }
    
            return response()->download(
                $path,
                basename($file),
                [
                    'Content-Type' =>
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]
            );
    }


}