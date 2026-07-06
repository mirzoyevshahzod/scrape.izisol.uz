<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ConvertEomborFilesController extends Controller
{
    public function convert(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $tempPath = storage_path('app/temp');

        if (!is_dir($tempPath)) {
            mkdir($tempPath, 0777, true);
        }

        $inputName = uniqid('input_') . '.' . $request->file('excel_file')->getClientOriginalExtension();
        $outputName = uniqid('output_') . '.xlsx';

        $inputPath = $tempPath . '/' . $inputName;
        $outputPath = $tempPath . '/' . $outputName;

        $request->file('excel_file')->move($tempPath, $inputName);

        Artisan::call('excel:convert-border', [
            'input'  => $inputPath,
            'output' => $outputPath,
        ]);

        $originalName = pathinfo(
            $request->file('excel_file')->getClientOriginalName(),
            PATHINFO_FILENAME
        );

        $downloadName = $originalName . '-formatted.xlsx';

        return response()->download(
            $outputPath,
            $downloadName,
            [
                'Access-Control-Expose-Headers' => 'Content-Disposition',
            ]
        )->deleteFileAfterSend(true);
    }
}