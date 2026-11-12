<?php

namespace Tests\Feature;

use App\Models\SpotEvent;
use App\Services\SpotEventsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class SpotEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();
        Carbon::setTestNow('2026-11-12 10:00:00');
        config([
            'spot_events.bot_token' => 'TEST',
            'spot_events.chat_id' => '123',
            'spot_events.keywords' => ['B2B', 'форум', 'бизнес-завтрак'],
        ]);
    }

    /** spot.uz/ru/events kartasi (tuzilishi haqiqiy sahifadan). $cards: [href, sarlavha, sana, kategoriya, shahar, narx|null] */
    public static function listPage(array $cards): string
    {
        $card = fn ($href, $title, $date, $cat, $city, $price = null) => '<div class="contentBox"><div class="itemCols"><div class="itemColData">'
            . '<div class="itemData"><span>' . "\n  {$date}   " . '<a class="itemCat" href="/ru/business/">' . $cat . '</a></span></div>'
            . '<h2 class="itemTitle"><a href="' . $href . '">' . $title . '</a></h2>'
            . '<div class="itemEventDetails"><div class="detailCol eventCity"><i></i><span>' . $city . '</span></div>'
            . ($price ? '<div class="detailCol eventAttendancePrice"><i></i><span>' . $price . '</span></div>' : '')
            . '<div class="clear"></div></div></div></div></div>';

        return '<html><head><meta charset="utf-8"></head><body><div class="row10"><div class="colp10-8">'
            . implode('', array_map(fn ($c) => $card(...$c), $cards))
            . '</div><div class="contentBox"><h2 class="itemTitle"><a href="/ru/2026/11/01/news/">Sidebar yangilik</a></h2></div>'
            . '</div></body></html>';
    }

    public static function detailPage(array $event): string
    {
        return '<html><head><meta charset="utf-8">'
            . '<script type="application/ld+json">{"@context":"https://schema.org","@type":"WebPage","name":"x"}</script>'
            . '<script type="application/ld+json">' . json_encode(['@context' => 'https://schema.org', '@type' => 'Event'] + $event, JSON_UNESCAPED_UNICODE) . '</script>'
            . '</head><body></body></html>';
    }

    private function cards(): array
    {
        return [
            ['/ru/2026/11/07/b2b-china/', 'B2B-встреча с представителями бизнеса Хайнань', '12 ноября  (четверг)', 'Бизнес', 'Ташкент', 'Бесплатно'],
            ['/ru/2026/11/06/murad-building/', 'Бизнес-завтрак с&nbsp;основателем Murad Buildings', '14 ноября  (суббота)', 'Бизнес', 'Ташкент', '400 000 сумов'],
            ['/ru/2026/10/29/new-uzbekistan-forum/', 'Международный бизнес-форум New Uzbekistan 2026', '29 ноября  &mdash; 6 декабря', 'Бизнес', 'Ташкент'],
            ['/ru/2026/11/09/pizza-pitch/', 'Конкурс стартапов PizzaPitch', '19 ноября  (четверг)', 'IT', 'Ташкент'],
            ['/ru/2026/11/01/old-forum/', 'Старый форум', '10 ноября  &mdash; 11 ноября', 'Бизнес', 'Ташкент'],
        ];
    }

    private ?string $listHtml = null;

    private bool $telegramUp = true;

    private int $seen = 0;

    private function fakeSite(): void
    {
        Http::fake([
            'www.spot.uz/ru/events/' => fn () => Http::response($this->listHtml ?? self::listPage($this->cards())),
            'www.spot.uz/ru/2026/11/06/murad-building/' => Http::response(self::detailPage([
                'startDate' => '2026-11-14T09:30:00+05:00',
                'description' => 'Участники смогут узнать о&nbsp;предпринимательском опыте.',
                'offers' => ['@type' => 'Offer', 'url' => 'https://t.me/Bekzod_BirinchiBC'],
            ])),
            'www.spot.uz/*' => Http::response(self::detailPage([])),
            'api.telegram.org/*' => fn () => $this->telegramUp ? Http::response(['ok' => true]) : Http::response(['ok' => false], 500),
        ]);
    }

    /** @return list<array> oxirgi chaqiruvdan beri Telegram'ga yuborilgan xabarlar */
    private function sentMessages(): array
    {
        $all = Http::recorded(fn (Request $r) => str_contains($r->url(), 'api.telegram.org') && $r->method() === 'POST')
            ->map(fn ($pair) => $pair[0]->data())
            ->values()
            ->all();
        $new = array_slice($all, $this->seen);
        $this->seen = count($all);

        return $new;
    }

    public function test_parses_event_cards_and_skips_other_blocks(): void
    {
        $events = SpotEventsClient::parseList(self::listPage($this->cards()), Carbon::today());

        $this->assertCount(5, $events);
        $this->assertSame([
            'url' => 'https://www.spot.uz/ru/2026/11/06/murad-building/',
            'title' => 'Бизнес-завтрак с основателем Murad Buildings',
            'date_text' => '14 ноября (суббота)',
            'category' => 'Бизнес',
            'city' => 'Ташкент',
            'price' => '400 000 сумов',
        ], collect($events[1])->except(['starts_on', 'ends_on'])->all());
        $this->assertSame('2026-11-14', $events[1]['starts_on']->toDateString());
        $this->assertSame('29 ноября — 6 декабря', $events[2]['date_text']);
        $this->assertSame('2026-12-06', $events[2]['ends_on']->toDateString());
        $this->assertNull($events[3]['price']);
    }

    public function test_date_parsing_picks_the_right_year(): void
    {
        $range = fn (string $text, string $today) => array_map(
            fn ($d) => $d->toDateString(),
            SpotEventsClient::parseDateRange($text, Carbon::parse($today)) ?? []
        );

        $this->assertSame(['2026-11-14', '2026-11-14'], $range('14 ноября (суббота)', '2026-11-12'));
        $this->assertSame(['2026-11-29', '2026-12-06'], $range('29 ноября — 6 декабря', '2026-11-12'));
        $this->assertSame(['2026-11-28', '2026-11-30'], $range('28 — 30 ноября', '2026-11-12'));
        $this->assertSame(['2027-01-15', '2027-01-15'], $range('15 января (пятница)', '2026-12-20'));
        $this->assertSame(['2026-12-28', '2027-01-03'], $range('28 декабря — 3 января', '2026-12-20'));
        $this->assertSame(['2026-12-28', '2027-01-03'], $range('28 декабря — 3 января', '2027-01-02'));
        $this->assertSame(['2026-11-10', '2026-11-12'], $range('10 ноября — 12 ноября', '2026-11-12'));
        $this->assertSame([], $range('скоро', '2026-11-12'));
        $this->assertSame([], $range('31 ноября', '2026-11-12'));
    }

    public function test_keyword_matching_ignores_case_hyphens_and_spaces(): void
    {
        $keywords = ['B2B', 'форум', 'бизнес-завтрак'];

        $this->assertSame('бизнес-завтрак', SpotEventsClient::matchKeyword('Бизнес завтрак с основателем', $keywords));
        $this->assertSame('бизнес-завтрак', SpotEventsClient::matchKeyword("Бизнес-завтрак\u{00A0}с основателем", $keywords));
        $this->assertSame('форум', SpotEventsClient::matchKeyword('Международный финтех-форум FINOBOD', $keywords));
        $this->assertSame('B2B', SpotEventsClient::matchKeyword('b2b-встреча с бизнесом', $keywords));
        $this->assertNull(SpotEventsClient::matchKeyword('Конкурс стартапов PizzaPitch', $keywords));
    }

    public function test_sends_matching_upcoming_events_once(): void
    {
        $this->fakeSite();

        $this->artisan('spot:events')->assertSuccessful();

        $messages = $this->sentMessages();
        $this->assertCount(3, $messages); // B2B (bugun), бизнес-завтрак, форум; o'tib ketgan forum va PizzaPitch — yo'q
        $this->assertSame('123', $messages[0]['chat_id']);
        $this->assertSame('HTML', $messages[0]['parse_mode']);
        $this->assertStringContainsString('B2B-встреча', $messages[0]['text']);

        $breakfast = $messages[1]['text'];
        $this->assertStringContainsString('<b>Бизнес-завтрак с основателем Murad Buildings</b>', $breakfast);
        $this->assertStringContainsString('14 ноября (суббота), 09:30', $breakfast);
        $this->assertStringContainsString('400 000 сумов', $breakfast);
        $this->assertStringContainsString('Участники смогут узнать о предпринимательском опыте.', $breakfast);
        $this->assertStringContainsString('https://t.me/Bekzod_BirinchiBC', $breakfast);
        $this->assertSame(['url' => 'https://www.spot.uz/ru/2026/11/06/murad-building/'], $messages[1]['link_preview_options']);

        $this->assertSame(5, SpotEvent::count());
        $this->assertSame(3, SpotEvent::whereNotNull('notified_at')->count());

        // Ikkinchi ishga tushirishda hech narsa qayta yuborilmaydi
        $this->artisan('spot:events')->assertSuccessful();
        $this->assertCount(0, $this->sentMessages());
    }

    public function test_reminds_the_day_before(): void
    {
        $this->fakeSite();
        $this->artisan('spot:events')->assertSuccessful();
        $this->sentMessages();

        // 13-noyabr tunda eslatma ketmaydi
        Carbon::setTestNow('2026-11-13 02:00:00');
        $this->artisan('spot:events')->assertSuccessful();
        $this->assertCount(0, $this->sentMessages());

        // 13-noyabr ertalab: 14-noyabrdagi бизнес-завтрак uchun eslatma (12-noyabrdagi B2B uchun emas)
        Carbon::setTestNow('2026-11-13 10:00:00');
        $this->artisan('spot:events')->assertSuccessful();

        $messages = $this->sentMessages();
        $this->assertCount(1, $messages);
        $this->assertStringStartsWith('⏰ <b>Ertaga:</b> Бизнес-завтрак', $messages[0]['text']);

        $this->artisan('spot:events')->assertSuccessful();
        $this->assertCount(0, $this->sentMessages());
    }

    public function test_event_announced_the_day_before_gets_no_separate_reminder(): void
    {
        Carbon::setTestNow('2026-11-13 10:00:00');
        $this->fakeSite();
        $this->artisan('spot:events')->assertSuccessful();

        $this->assertNotNull(SpotEvent::where('url', 'like', '%murad-building%')->first()->reminded_at);
    }

    public function test_failed_send_is_retried_next_run(): void
    {
        $this->listHtml = self::listPage([$this->cards()[0]]);
        $this->telegramUp = false;
        $this->fakeSite();

        $this->artisan('spot:events')->assertFailed();
        $this->assertSame(0, SpotEvent::whereNotNull('notified_at')->count());

        $this->telegramUp = true;
        $this->artisan('spot:events')->assertSuccessful();
        $this->assertSame(1, SpotEvent::whereNotNull('notified_at')->count());
    }

    public function test_alerts_once_a_day_when_page_has_no_events(): void
    {
        $this->listHtml = '<html><body><div class="contentBox">Yangi dizayn</div></body></html>';
        $this->fakeSite();

        $this->artisan('spot:events')->assertFailed();
        $this->artisan('spot:events')->assertFailed();

        $messages = $this->sentMessages();
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('bitta ham tadbir topilmadi', $messages[0]['text']);
    }
}
