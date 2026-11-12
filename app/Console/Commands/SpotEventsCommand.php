<?php

namespace App\Console\Commands;

use App\Models\SpotEvent;
use App\Services\SpotEventsClient;
use App\Support\Telegram\TelegramSender;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * spot.uz/ru/events sahifasidagi tadbirlarni ko'rib chiqadi:
 *  1) hammasini spot_events jadvaliga yozadi (url bo'yicha upsert);
 *  2) sarlavhasida kalit so'z bor (config/spot_events.php), hali tugamagan va
 *     hali yuborilmagan tadbirlarni Telegram'ga e'lon qiladi;
 *  3) ertaga boshlanadigan, e'lon qilingan tadbirlar uchun eslatma yuboradi
 *     (09:00 dan keyin; buyruq har 2 soatda ishlaydi).
 * Sahifadan tadbir chiqmasa yoki sayt ochilmasa, kuniga bir marta ogohlantiradi.
 */
class SpotEventsCommand extends Command
{
    protected $signature = 'spot:events {--dry-run : Telegram\'ga yubormaydi, faqat nima yuborilishini ko\'rsatadi}';

    protected $description = "spot.uz tadbirlaridan kerakli e'lonlarni va eslatmalarni Telegram'ga yuboradi";

    /** "Ertaga" eslatmalari shu soatdan keyin yuboriladi */
    private const REMIND_FROM_HOUR = 9;

    private TelegramSender $telegram;

    private string $chatId;

    public function handle(SpotEventsClient $client): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $this->chatId = (string) config('spot_events.chat_id');
        $token = (string) config('spot_events.bot_token');

        if (! $dryRun && ($token === '' || $this->chatId === '')) {
            $this->error(".env'da SPOT_EVENTS_BOT_TOKEN va SPOT_EVENTS_CHAT_ID berilmagan.");

            return self::FAILURE;
        }

        $this->telegram = new TelegramSender($token);
        $keywords = config('spot_events.keywords');

        try {
            $events = $client->events();
        } catch (Throwable $e) {
            Log::error('spot:events — ro\'yxatni olib bo\'lmadi: ' . $e->getMessage());
            $this->alertOnce("⚠️ spot.uz/ru/events ochilmadi: {$e->getMessage()}", $dryRun);

            return self::FAILURE;
        }

        if ($events === []) {
            $this->alertOnce("⚠️ spot.uz/ru/events sahifasidan bitta ham tadbir topilmadi — sayt markupi o'zgargan bo'lishi mumkin.", $dryRun);

            return self::FAILURE;
        }

        $listedUrls = [];

        foreach ($events as $data) {
            $event = SpotEvent::firstOrNew(['url' => $data['url']]);

            // JSON-LD'dan olingan aniq sanani saytdagi sana o'zgarmaguncha saqlab qolamiz
            if ($event->date_text !== $data['date_text'] || ! $event->starts_on) {
                $event->starts_on = $data['starts_on'];
                $event->ends_on = $data['ends_on'];
            }

            $event->fill([
                'title' => $data['title'],
                'category' => $data['category'],
                'city' => $data['city'],
                'price' => $data['price'],
                'date_text' => $data['date_text'],
                'matched_keyword' => SpotEventsClient::matchKeyword($data['title'], $keywords),
            ]);
            $event->touch();
            $listedUrls[] = $event->url;
        }

        $this->info(count($events) . ' ta tadbir topildi.');

        $failed = $this->announce($client, $listedUrls, $dryRun) + $this->remind($dryRun);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return int yuborib bo'lmagan xabarlar soni */
    private function announce(SpotEventsClient $client, array $listedUrls, bool $dryRun): int
    {
        $today = Carbon::today();
        $events = SpotEvent::whereIn('url', $listedUrls)
            ->whereNotNull('matched_keyword')
            ->whereNull('notified_at')
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today))
            ->orderBy('starts_on')
            ->get();

        $failed = 0;

        foreach ($events as $event) {
            $this->enrich($client, $event);

            if ($dryRun) {
                $this->line("[e'lon] {$event->title} ({$event->matched_keyword})");

                continue;
            }

            if (! $this->send($this->announcement($event), $event->url)) {
                $failed++;

                continue;
            }

            $event->notified_at = now();
            // ertaga yoki bugun bo'ladigan tadbir uchun e'lonning o'zi eslatma
            if ($event->starts_on && $event->starts_on->lte($today->copy()->addDay())) {
                $event->reminded_at = now();
            }
            $event->save();
        }

        $this->info($events->count() . " ta yangi e'lon" . ($failed ? ", {$failed} tasi yuborilmadi" : '') . '.');

        return $failed;
    }

    /** @return int yuborib bo'lmagan xabarlar soni */
    private function remind(bool $dryRun): int
    {
        // Buyruq har 2 soatda ishlaydi — eslatma tunda emas, ertalabdan boshlab ketsin
        if (now()->hour < self::REMIND_FROM_HOUR) {
            return 0;
        }

        $events = SpotEvent::whereNotNull('notified_at')
            ->whereNull('reminded_at')
            ->whereDate('starts_on', Carbon::tomorrow())
            ->get();

        $failed = 0;

        foreach ($events as $event) {
            if ($dryRun) {
                $this->line("[eslatma] {$event->title}");

                continue;
            }

            if (! $this->send($this->reminder($event), $event->url)) {
                $failed++;

                continue;
            }

            $event->update(['reminded_at' => now()]);
        }

        $this->info($events->count() . ' ta eslatma.');

        return $failed;
    }

    /** Tadbir sahifasidan (JSON-LD) aniq sana/vaqt, tavsif va ro'yxatdan o'tish linki */
    private function enrich(SpotEventsClient $client, SpotEvent $event): void
    {
        try {
            $details = $client->details($event->url);
        } catch (Throwable $e) {
            Log::warning("spot:events — {$event->url} sahifasini olib bo'lmadi: " . $e->getMessage());

            return;
        }

        if ($details['starts_at']) {
            $event->starts_on = $details['starts_at']->copy()->startOfDay();
            $event->start_time = $details['starts_at']->format('H:i') !== '00:00' ? $details['starts_at']->format('H:i') : null;
            $event->ends_on = $details['ends_at']?->copy()->startOfDay() ?? $event->ends_on ?? $event->starts_on;
        }

        $event->description = $details['description'] ?? $event->description;
        $event->registration_url = $details['registration_url'] ?? $event->registration_url;
        $event->save();
    }

    private function announcement(SpotEvent $event): string
    {
        $lines = ['📢 <b>' . e($event->title) . '</b>', ''];
        $lines[] = '📅 ' . e($this->dateLine($event));

        foreach (['📍' => $event->city, '💰' => $event->price, '🏷' => $event->category] as $icon => $value) {
            if ($value) {
                $lines[] = $icon . ' ' . e($value);
            }
        }

        if ($event->description) {
            $lines[] = '';
            $lines[] = e(mb_strimwidth($event->description, 0, 500, '…'));
        }

        $lines[] = '';

        if ($event->registration_url) {
            $lines[] = "📝 Ro'yxatdan o'tish: " . e($event->registration_url);
        }

        $lines[] = '🔗 <a href="' . e($event->url) . '">spot.uz\'da batafsil</a>';

        return implode("\n", $lines);
    }

    private function reminder(SpotEvent $event): string
    {
        $lines = ['⏰ <b>Ertaga:</b> ' . e($event->title), ''];
        $lines[] = '📅 ' . e($this->dateLine($event));

        if ($event->city) {
            $lines[] = '📍 ' . e($event->city);
        }

        if ($event->registration_url) {
            $lines[] = "📝 Ro'yxatdan o'tish: " . e($event->registration_url);
        }

        $lines[] = '🔗 <a href="' . e($event->url) . '">spot.uz\'da batafsil</a>';

        return implode("\n", $lines);
    }

    private function dateLine(SpotEvent $event): string
    {
        $date = $event->date_text ?: $event->starts_on?->format('d.m.Y') ?? "noma'lum";

        return $event->start_time ? "{$date}, {$event->start_time}" : $date;
    }

    private function send(string $html, string $previewUrl): bool
    {
        $ok = $this->telegram->sendMessage($this->chatId, $html, [
            'parse_mode' => 'HTML',
            'link_preview_options' => ['url' => $previewUrl],
        ]);

        if (! $ok) {
            Log::warning("spot:events — Telegram'ga yuborilmadi: {$previewUrl}");
        }

        Sleep::for(1)->second(); // Telegram: bitta chatga sekundiga ~1 xabar

        return $ok;
    }

    /** Bir xil muammo haqida kuniga bir marta ogohlantiradi */
    private function alertOnce(string $message, bool $dryRun): void
    {
        $this->error($message);

        if ($dryRun || ! Cache::add('spot-events-alert:' . Carbon::today()->toDateString(), true, Carbon::tomorrow())) {
            return;
        }

        $this->telegram->sendMessage($this->chatId, $message);
    }
}
