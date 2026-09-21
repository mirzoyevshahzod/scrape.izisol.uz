<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ConvertEomborFilesController extends Controller
{
    public function convert(Request $request)
    {
        // kod ichida, faqat shu skript uchun
        ini_set('memory_limit', '512M');
        $request->validate([
            'excel_files' => 'required|array|min:1',
            'excel_files.*' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $tempPath = storage_path('app/temp');

        if (!is_dir($tempPath)) {
            mkdir($tempPath, 0777, true);
        }

        $inputPaths = [];

        try {

            /*
             * Upload qilingan fayllarni temp papkaga saqlash
             */
            foreach ($request->file('excel_files') as $file) {

                $inputName = uniqid('input_') . '.' .
                    $file->getClientOriginalExtension();

                $file->move($tempPath, $inputName);

                $inputPath = $tempPath . '/' . $inputName;

                $inputPaths[] = $inputPath;
            }

            /*
             * Output
             */
            $outputPath = $tempPath . '/' .
                uniqid('output_') . '.xlsx';

            /*
             * Artisan command
             */
            $exitCode = Artisan::call(
                'excel:convert-border',
                [
                    'inputs' => $inputPaths,
                    '--output' => $outputPath,
                ]
            );

            /*
             * Command logini olish
             */
            $artisanOutput = Artisan::output();

            Log::info('E-ombor command result', [
                'exit_code' => $exitCode,
                'inputs_count' => count($inputPaths),
                'output' => $artisanOutput,
            ]);

            /*
             * Command xato bilan tugagan bo'lsa
             */
            if ($exitCode !== 0) {

                throw new \RuntimeException(
                    "Excel command xato bilan tugadi.\n\n" .
                    $artisanOutput
                );
            }

            /*
             * Output yaratilganini tekshirish
             */
            if (!file_exists($outputPath)) {

                throw new \RuntimeException(
                    "Output Excel fayl yaratilmadi.\n\n" .
                    $artisanOutput
                );
            }

            /*
             * Input fayllarni o'chirish
             */
            foreach ($inputPaths as $inputPath) {

                if (file_exists($inputPath)) {
                    unlink($inputPath);
                }
            }

            /*
             * Download
             */
            return response()
                ->download(
                    $outputPath,
                    'e-ombor-formatted.xlsx',
                    [
                        'Access-Control-Expose-Headers' =>
                            'Content-Disposition',
                    ]
                )
                ->deleteFileAfterSend(true);

        } catch (\Throwable $e) {

            /*
             * Agar xatolik bo'lsa input fayllarni o'chirish
             */
            foreach ($inputPaths as $inputPath) {

                if (file_exists($inputPath)) {
                    unlink($inputPath);
                }
            }

            Log::error('E-ombor convert error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Excel fayllarni formatlashda xatolik.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}