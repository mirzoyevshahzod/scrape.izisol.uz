<?php

namespace App\Jobs;

use App\Models\JobRun;
use App\Support\Operations\JobRunOutput;
use App\Support\Operations\ScheduleLock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * "Boshqaruv" sahifasidagi bitta amalni (config/operations.php) bajaradi:
 * buyruqlarni ketma-ket ishga tushirib, holat va chiqishni JobRun'ga yozadi.
 *
 * Buyruqlar bir-biridan mustaqil — bittasi yiqilsa ham qolganlari davom
 * etadi, lekin umumiy natija "failed" bo'ladi.
 */
class RunOperationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /** config/queue.php'dagi retry_after (7200) dan kichik bo'lishi shart */
    public int $timeout = 7000;

    public bool $failOnTimeout = true;

    public function __construct(public JobRun $run)
    {
        $this->onQueue('operations');
    }

    public function handle(): void
    {
        $this->run->update(['status' => JobRun::RUNNING, 'started_at' => now()]);

        $output = new JobRunOutput($this->run);
        $failed = false;

        foreach (self::commandsFor($this->run) as [$command, $parameters]) {
            $display = trim($command . ' ' . implode(' ', array_map(
                fn ($key, $value) => str_starts_with((string) $key, '--') ? "{$key}={$value}" : "\"{$value}\"",
                array_keys($parameters),
                $parameters
            )));
            $output->writeln("\n▶ php artisan {$display}  [" . now()->format('H:i:s') . ']');

            $lock = new ScheduleLock($command, $parameters);

            if (! $lock->acquire()) {
                $output->writeln('⏭ Jadval bo\'yicha hozir ishlayapti — o\'tkazib yuborildi.');
                $failed = true;

                continue;
            }

            try {
                $code = Artisan::call($command, $parameters, $output);
            } catch (Throwable $e) {
                $output->writeln('Xato: ' . $e->getMessage());
                $code = 1;
            } finally {
                $lock->release();
            }

            $output->writeln(($code === 0 ? '✔' : '✖') . " tugadi (kod {$code})  [" . now()->format('H:i:s') . ']');
            $output->flush();

            $failed = $failed || $code !== 0;
        }

        $output->flush();

        $this->run->update([
            'status' => $failed ? JobRun::FAILED : JobRun::SUCCESS,
            'finished_at' => now(),
        ]);
    }

    /**
     * Amalning buyruqlari; ishga tushirishda kun tanlangan bo'lsa, har
     * biriga --date=YYYY-MM-DD qo'shiladi.
     *
     * @return array<int,array{0:string,1:array}>
     */
    public static function commandsFor(JobRun $run): array
    {
        $date = $run->params['date'] ?? null;

        return array_map(
            fn ($command) => [$command[0], $date ? $command[1] + ['--date' => $date] : $command[1]],
            config("operations.operations.{$run->operation}.commands", [])
        );
    }

    /**
     * Timeout yoki worker xatosi — ish "ishlayapti" holatida qolib ketmasin
     * va egallangan jadval mutex'lari bo'shatilsin.
     */
    public function failed(?Throwable $exception): void
    {
        foreach (self::commandsFor($this->run) as [$command, $parameters]) {
            (new ScheduleLock($command, $parameters))->release();
        }

        $run = $this->run->fresh() ?? $this->run;
        $run->appendOutput("\n✖ Ish to'xtadi: " . ($exception?->getMessage() ?? 'noma\'lum xato') . "\n");
        $run->status = JobRun::FAILED;
        $run->finished_at = now();
        $run->save();
    }
}
