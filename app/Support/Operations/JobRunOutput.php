<?php

namespace App\Support\Operations;

use App\Models\JobRun;
use Symfony\Component\Console\Output\Output;

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
        $this->run->save();
        $this->buffer = '';
        $this->lastFlush = microtime(true);
    }
}
