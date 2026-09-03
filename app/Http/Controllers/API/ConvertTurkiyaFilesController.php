<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class ConvertTurkiyaFilesController extends Controller
{
    public function convert(Request $request)
    {
        $request->validate([
            'file' => 'required|string',
        ]);

        $fileName = $request->input('file');

        // TODO: bu papkani sizning /api/turkey/files
        // qayerdan olayotgan bo'lsa, o'sha joy bilan bir xil qilish kerak
        $input = storage_path('app/turkey/' . $fileName);

        if (!File::exists($input)) {
            return response()->json([
                'status' => false,
                'message' => 'Fayl topilmadi',
                'file' => $fileName,
                'path' => $input,
            ], 404);
        }

        File::ensureDirectoryExists(
            storage_path('app/temp')
        );

        $originalName = pathinfo(
            $fileName,
            PATHINFO_FILENAME
        );

        $outputName = $originalName . '_convert.xlsx';

        $output = storage_path(
            'app/temp/' . $outputName
        );

        Artisan::call('excel:border-convert', [
            'input' => $input,
            'output' => $output,
        ]);

        if (!File::exists($output)) {
            return response()->json([
                'status' => false,
                'message' => 'Convert natijasida fayl yaratilmadi',
            ], 500);
        }

        return response()->download(
            $output,
            $outputName
        )->deleteFileAfterSend(true);
    }
}