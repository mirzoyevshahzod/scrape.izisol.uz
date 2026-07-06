<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ProcessMintransExcel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $filePath;
    protected string $name;

    /**
     * Yangi Job yaratiladi
     */
    public function __construct(string $filePath, string $name)
    {
        $this->filePath = $filePath;
        $this->name = $name;
    }

    /**
     * Job bajarilganda ishga tushadigan kod
     */
    public function handle(): void
    {
        Log::info('ProcessMintransExcel: Job boshlandi. File: ' . $this->filePath);

        try {
            // Artisan command-ni ishga tushirish
            Artisan::call('mintrans:scrape', [
                'filePath' => $this->filePath,
                'name' => $this->name,
            ]);

            Log::info('ProcessMintransExcel: Command muvaffaqiyatli bajarildi.');
        } catch (\Throwable $e) {
            Log::error('ProcessMintransExcel: Xato yuz berdi: ' . $e->getMessage());
        }

        // Faylni tozalash (xohlovga ko‘ra)
        if (file_exists($this->filePath)) {
            unlink($this->filePath);
            Log::info('ProcessMintransExcel: Vaqtincha fayl o‘chirildi: ' . $this->filePath);
        }
    }
}
