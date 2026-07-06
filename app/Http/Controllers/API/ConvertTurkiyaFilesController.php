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
            'excel_file' => 'required|file|mimes:xlsx,xls',
        ]);

        File::ensureDirectoryExists(storage_path('app/temp'));

        // Original fayl nomi
        $originalName = pathinfo(
            $request->file('excel_file')->getClientOriginalName(),
            PATHINFO_FILENAME
        );

        // Serverda saqlanadigan input fayl
        $inputName = time().'_'.$request->file('excel_file')->getClientOriginalName();
        $request->file('excel_file')->move(storage_path('app/temp'), $inputName);

        $input = storage_path('app/temp/'.$inputName);

        // Serverdagi output fayl
        $outputName = $originalName.'_convert.xlsx';
        $output = storage_path('app/temp/'.$outputName);

        Artisan::call('excel:border-convert', [
            'input'  => $input,
            'output' => $output,
        ]);

        return response()->download(
            $output,
            $outputName
        )->deleteFileAfterSend(true);
    }
}
