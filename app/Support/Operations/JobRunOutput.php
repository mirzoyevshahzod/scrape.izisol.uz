<?php

namespace App\Support\Operations;

use App\Models\JobRun;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Output\Output;
use Throwable;

/**
 * Artisan buyrug'ining chiqishini JobRun->output'ga yozib boradi.
 * Bazaga har yozuvda emas, ko'pi bilan har 2 soniyada saqlanadi — sahifa
 * uzoq ishlarning natijasini jarayon davomida ko'rib turadi.
 */
class JobRunOutput extends Output
{
    private string $buffer = '';

    private float $lastFlush = 0;

    public function __construct(private JobRun $run)
    {
        parent::__construct(self::VERBOSITY_NORMAL, false);
    }

    protected function doWrite(string $message, bool $newline): void
    {
        $this->buffer .= $message . ($newline ? "\n" : '');

        if (microtime(true) - $this->lastFlush >= 2) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if ($this->buffer === '') {
            return;
        }

        $this->run->appendOutput($this->buffer);
        $this->buffer = '';
        $this->lastFlush = microtime(true);

        // log — yordamchi ma'lumot: saqlab bo'lmasa ham buyruq (asosiy ish) to'xtamasin
        try {
            $this->run->save();
        } catch (Throwable $e) {
            Log::warning('JobRun output saqlanmadi', ['run' => $this->run->id, 'error' => $e->getMessage()]);
        }
    }
}
