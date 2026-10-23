<?php

namespace App\Support\Operations;

use Illuminate\Console\Application;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ProcessUtils;

/**
 * Tugma orqali ishga tushirilgan buyruq va routes/console.php jadvalidagi
 * xuddi shu buyruq bir vaqtda ishlamasligi uchun jadvalning o'z
 * `withoutOverlapping()` mutex'idan foydalanadi:
 *  - jadval bo'yicha shu buyruq hozir ishlayotgan bo'lsa, acquire() false;
 *  - aks holda mutex egallanadi va jadval shu vaqt ichida uni o'tkazib yuboradi.
 *
 * Jadvaldagi vazifani topishda `--date` kabi opsiyalar (kalitlari "--"
 * bilan boshlanadi) hisobga olinmaydi: `crm:daily-import --date=...` ham
 * jadvaldagi `crm:daily-import` bilan bir qulfni ishlatadi.
 *
 * Mutex kaliti buyruq satriga (PHP binary yo'li bilan) bog'liq — shuning
 * uchun queue worker cron'dagi `schedule:run` bilan bir xil PHP binary'da
 * ishga tushirilishi kerak.
 */
class ScheduleLock
{
    /** Worker o'chib qolsa, mutex shuncha daqiqadan keyin o'zi tushadi */
    private const TTL_MINUTES = 120;

    private ?Event $event;

    public function __construct(string $command, array $parameters)
    {
        $this->event = self::findEvent($command, $parameters);
    }

    /** Jadvalda bu buyruq bo'lmasa ham true (to'sadigan narsa yo'q) */
    public function acquire(): bool
    {
        return $this->event === null || $this->event->mutex->create($this->event);
    }

    public function release(): void
    {
        $this->event?->mutex->forget($this->event);
    }

    private static function findEvent(string $command, array $parameters): ?Event
    {
        $parameters = array_filter($parameters, fn ($key) => ! str_starts_with((string) $key, '--'), ARRAY_FILTER_USE_KEY);

        $expected = trim(Application::formatCommandString($command) . ' ' . implode(' ', array_map(
            fn ($value) => ProcessUtils::escapeArgument((string) $value),
            array_values($parameters)
        )));

        foreach (app(Schedule::class)->events() as $event) {
            if ($event->command === $expected) {
                // mutex kaliti expiresAt'ga bog'liq emas — nusxada TTL'ni qisqartiramiz
                $event = clone $event;
                $event->expiresAt = self::TTL_MINUTES;

                return $event;
            }
        }

        return null;
    }
}
