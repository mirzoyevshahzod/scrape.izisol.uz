<?php

namespace App\Console\Commands;

use App\Http\Controllers\API\BelarusQueueController;
use App\Services\DeclarantQueue;
use App\Support\Telegram\TelegramSender;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Har daqiqa (routes/console.php):
 *  1. brauzerdan kelgan navbat javoblaridan (BelarusQueueController) Excel
 *     tayyorlaydi va Declarant'ni CRM'ga yuklaydi (hisobot Telegram'ga);
 *  2. biror zona ma'lumoti eskirsa (captcha yechilmagan) — Telegram'ga
 *     masofaviy brauzer havolasi bilan "captcha'ni yeching" eslatmasi.
 */
class BelarusQueueSyncCommand extends Command
{
    protected $signature = 'belarus:queue-sync';

    protected $description = 'Brauzerdan kelgan Belarus navbatini CRM\'ga yuklaydi, eskirsa captcha eslatmasini yuboradi';

    public const LAST_KEY = 'belarus-queue:last:';

    private const REMINDED_KEY = 'belarus-queue:reminded';

    /** Zonalar ketma-ket keladi — hammasi kelguncha (ko'pi bilan shuncha soniya) kutiladi, CRM'ga bitta import ketsin */
    private const GATHER_SECONDS = 90;

    public function handle(DeclarantQueue $queue): int
    {
        if ($this->exportPending($queue)) {
            $this->call('crm:daily-import', ['--only' => 'declarant', '--notify' => true]);
        }

        $this->remindIfStale();

        return self::SUCCESS;
    }

    private function exportPending(DeclarantQueue $queue): bool
    {
        $pending = [];

        foreach (array_keys(DeclarantQueue::ZONES) as $zone) {
            if ($item = Cache::get(BelarusQueueController::PENDING_KEY . $zone)) {
                $pending[$zone] = $item;
            }
        }

        if (! $pending) {
            return false;
        }

        $waiting = array_diff(config('belarus_queue.zones'), array_keys($pending));
        $oldest = min(array_column($pending, 'received_at'));

        if ($waiting && $oldest > now()->getTimestamp() - self::GATHER_SECONDS) {
            $this->line('Kutilmoqda: ' . implode(', ', $waiting));

            return false;
        }

        foreach ($pending as $zone => $item) {
            Cache::forget(BelarusQueueController::PENDING_KEY . $zone);
            $result = $queue->export($zone, $item['data']);

            Cache::forever(self::LAST_KEY . $zone, ['at' => $item['received_at'], 'queue' => $result['queue'], 'rows' => $result['rows']]);
            $this->info(DeclarantQueue::ZONES[$zone]['name'] . ": navbatda {$result['queue']} ta, o'zbek raqamli {$result['rows']} ta — {$result['path']}");
        }

        return true;
    }

    private function remindIfStale(): void
    {
        $staleAfter = now()->subMinutes(config('belarus_queue.stale_minutes'))->getTimestamp();
        $lines = [];

        foreach (config('belarus_queue.zones') as $zone) {
            $last = Cache::get(self::LAST_KEY . $zone);

            if (! $last || $last['at'] < $staleAfter) {
                $name = DeclarantQueue::ZONES[$zone]['name'] ?? $zone;
                $lines[] = "— {$name}: " . ($last ? 'oxirgi ma\'lumot ' . date('d.m H:i', $last['at']) : 'hali ma\'lumot kelmagan');
            }
        }

        if (! $lines || ! $this->inRemindHours()) {
            return;
        }

        if (! Cache::add(self::REMINDED_KEY, true, now()->addMinutes(config('belarus_queue.remind_every_minutes')))) {
            return;
        }

        $url = config('belarus_queue.vnc_url');
        $text = "🔐 <b>Belarus navbati yangilanmayapti</b>\n" . htmlspecialchars(implode("\n", $lines), ENT_NOQUOTES) . "\n\n"
            . ($url
                ? "Tugmani bosing, ochilgan brauzer oynasida captcha katagini belgilang — ma'lumot o'zi CRM'ga ketadi."
                : 'Serverdagi brauzerda captcha\'ni yeching (BELARUS_QUEUE_VNC_URL sozlanmagan).');

        $sent = (new TelegramSender())->sendMessage((string) config('services.telegram.report_chat_id'), $text, array_filter([
            'parse_mode' => 'HTML',
            'reply_markup' => $url ? ['inline_keyboard' => [[['text' => "🔓 Captcha'ni yechish", 'url' => $url]]]] : null,
        ]));

        if ($sent) {
            $this->warn('Captcha eslatmasi yuborildi.');
        } else {
            Cache::forget(self::REMINDED_KEY); // keyingi daqiqada qayta urinadi
            $this->warn('Captcha eslatmasi yuborilmadi (Telegram javob bermadi).');
        }
    }

    private function inRemindHours(): bool
    {
        [$from, $to] = array_map('intval', explode('-', config('belarus_queue.remind_hours')) + [0, 24]);
        $hour = now()->hour;

        return $from <= $to ? ($hour >= $from && $hour < $to) : ($hour >= $from || $hour < $to);
    }
}
