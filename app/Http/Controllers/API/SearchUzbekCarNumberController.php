<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SearchUzbekCarNumberController extends Controller
{
    public function filterUzPlates(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        try {

            $uploadedFile = $request->file('excel_file');

            $inputPath = $uploadedFile->getRealPath();

            $originalName = pathinfo(
                $uploadedFile->getClientOriginalName(),
                PATHINFO_FILENAME
            );

            $downloadName = $originalName . '.xlsx';

            $outputPath = storage_path('app/' . uniqid() . '_' . $downloadName);

            Artisan::call('plates:extract', [
                'input'  => $inputPath,
                'output' => $outputPath,
            ]);

            return response()->download($outputPath, $downloadName)
                ->deleteFileAfterSend(true);

        } catch (\Throwable $e) {

            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }
}
