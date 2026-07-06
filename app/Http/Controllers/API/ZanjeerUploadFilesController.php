<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

class ZanjeerUploadFilesController extends Controller
{
 

    public function downloadBorderZip(Request $request)
    {
        
        $request->validate([
            'file' => 'required|string',
        ]);

        $filePath = storage_path('app/import_qozoq/' . $request->input('file'));

        if (!File::exists($filePath)) {
            return response()->json([
                'status' => false,
                'message' => 'Fayl topilmadi.'
            ], Response::HTTP_NOT_FOUND);
        }

        Artisan::call('excel:split', [
            'file' => $filePath,
        ]);

        $zip = collect(File::files(storage_path('app/exports')))
            ->filter(fn ($file) => $file->getExtension() === 'zip')
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->first();

        if (!$zip) {
            return response()->json([
                'status' => false,
                'message' => 'ZIP fayl yaratilmagan.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $originalName = pathinfo($request->input('file'), PATHINFO_FILENAME);

        return response()->download(
            $zip->getPathname(),
            $originalName . '.zip'
        )->deleteFileAfterSend(true);
    }
}
