<?php

namespace App\Services;

use DOMNode;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

/**
 * https://www.spot.uz/ru/events/ sahifasidagi tadbirlar ro'yxati.
 *
 * API yo'q, sahifa server-rendered HTML: bitta GET yetarli, ~20 ta yaqin
 * tadbir chiqadi (pagination yo'q). Har bir karta — `.contentBox` ichida
 * `.itemEventDetails` bor blok. Sanada yil yo'q ("14 ноября (суббота)",
 * "29 ноября — 6 декабря"), shuning uchun yil bugungi sanaga qarab tanlanadi.
 * Tadbir sahifasidagi JSON-LD (`@type: Event`) dan aniq sana, tavsif va
 * ro'yxatdan o'tish linki olinadi.
 */
class SpotEventsClient
{
    public const BASE_URL = 'https://www.spot.uz';

    public const LIST_URL = self::BASE_URL . '/ru/events/';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

    private const MONTHS = [
        'января' => 1, 'февраля' => 2, 'марта' => 3, 'апреля' => 4, 'мая' => 5, 'июня' => 6,
        'июля' => 7, 'августа' => 8, 'сентября' => 9, 'октября' => 10, 'ноября' => 11, 'декабря' => 12,
    ];

    /**
     * @return list<array{url: string, title: string, date_text: ?string, starts_on: ?Carbon, ends_on: ?Carbon, category: ?string, city: ?string, price: ?string}>
     *
     * @throws RequestException|ConnectionException
     */
    public function events(): array
    {
        return self::parseList($this->get(self::LIST_URL), Carbon::today());
    }

    /**
     * @return array{starts_at: ?Carbon, ends_at: ?Carbon, description: ?string, registration_url: ?string}
     *
     * @throws RequestException|ConnectionException
     */
    public function details(string $url): array
    {
        return self::parseDetails($this->get($url));
    }

    private function get(string $url): string
    {
        $response = Http::timeout(15)
            ->connectTimeout(10)
            ->retry(3, fn (int $attempt) => 2000 * 2 ** ($attempt - 1), fn ($e) => $e instanceof ConnectionException
                || ($e instanceof RequestException && $e->response->serverError()))
            ->withHeaders(['User-Agent' => self::USER_AGENT, 'Accept-Language' => 'ru'])
            ->get($url);

        $response->throw();

        return $response->body();
    }

    public static function parseList(string $html, Carbon $today): array
    {
        $events = [];

        (new Crawler($html, self::LIST_URL))->filter('.contentBox')->each(function (Crawler $box) use (&$events, $today) {
            $link = $box->filter('h2.itemTitle a');

            if (! $box->filter('.itemEventDetails')->count() || ! $link->count()) {
                return;
            }

            $span = $box->filter('.itemData > span');
            $dateText = $span->count() ? self::ownText($span->getNode(0)) : '';
            [$startsOn, $endsOn] = self::parseDateRange($dateText, $today) ?? [null, null];
            $url = self::absoluteUrl((string) $link->attr('href'));

            $events[$url] = [
                'url' => $url,
                'title' => self::clean($link->text()),
                'date_text' => $dateText !== '' ? $dateText : null,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'category' => self::optionalText($box, '.itemData a.itemCat'),
                'city' => self::optionalText($box, '.eventCity span'),
                'price' => self::optionalText($box, '.eventAttendancePrice span'),
            ];
        });

        return array_values($events);
    }

    /**
     * "14 ноября (суббота)", "12 ноября — 14 ноября", "29 ноября — 6 декабря", "28 — 30 ноября".
     * Yil: tugash sanasi bugundan 3 oydan ko'proq oldin chiqsa — keyingi yil
     * (dekabrda turib "января" — kelasi yil); boshlanish sanasi tugashdan keyin
     * chiqsa — bir yil oldin ("28 декабря — 3 января").
     *
     * @return array{Carbon, Carbon}|null
     */
    public static function parseDateRange(string $text, Carbon $today): ?array
    {
        $text = preg_replace('/\([^)]*\)/u', '', mb_strtolower(self::clean($text)));
        $parts = array_values(array_filter(array_map('trim', preg_split('/[—–-]/u', $text)), fn ($p) => $p !== ''));

        if (count($parts) < 1 || count($parts) > 2) {
            return null;
        }

        $dates = [];

        foreach ($parts as $part) {
            if (! preg_match('/^(\d{1,2})(?:\s+(\p{L}+))?/u', $part, $m)) {
                return null;
            }

            $month = isset($m[2]) ? (self::MONTHS[$m[2]] ?? false) : null;

            if ($month === false) {
                return null;
            }

            $dates[] = [(int) $m[1], $month];
        }

        [$endDay, $endMonth] = end($dates);
        [$startDay, $startMonth] = $dates[0];
        $startMonth ??= $endMonth;

        if ($endMonth === null) {
            return null;
        }

        $end = self::date($today->year, $endMonth, $endDay);

        if ($end === null || $end->lt($today->copy()->startOfDay()->subMonths(3))) {
            $end = self::date($today->year + 1, $endMonth, $endDay);
        }

        if ($end === null) {
            return null;
        }

        $startYear = $end->year;

        if ($startMonth > $endMonth || ($startMonth === $endMonth && $startDay > $endDay)) {
            $startYear--;
        }

        $start = self::date($startYear, $startMonth, $startDay);

        return $start ? [$start, $end] : null;
    }

    private static function date(int $year, int $month, int $day): ?Carbon
    {
        return checkdate($month, $day, $year) ? Carbon::create($year, $month, $day)->startOfDay() : null;
    }

    /**
     * Tadbir sahifasidagi JSON-LD `Event` obyekti.
     *
     * @return array{starts_at: ?Carbon, ends_at: ?Carbon, description: ?string, registration_url: ?string}
     */
    public static function parseDetails(string $html): array
    {
        $result = ['starts_at' => null, 'ends_at' => null, 'description' => null, 'registration_url' => null];

        $scripts = (new Crawler($html))->filter('script[type="application/ld+json"]')->each(fn (Crawler $s) => $s->text(null, false));

        foreach ($scripts as $json) {
            $data = json_decode($json, true);

            if (! is_array($data)) {
                continue;
            }

            $items = isset($data['@graph']) ? $data['@graph'] : (array_is_list($data) ? $data : [$data]);

            foreach ($items as $item) {
                if (! is_array($item) || ($item['@type'] ?? null) !== 'Event') {
                    continue;
                }

                $offers = $item['offers'] ?? [];
                $offer = is_array($offers) && array_is_list($offers) ? ($offers[0] ?? []) : $offers;

                return [
                    'starts_at' => self::parseIsoDate($item['startDate'] ?? null),
                    'ends_at' => self::parseIsoDate($item['endDate'] ?? null),
                    'description' => is_string($item['description'] ?? null) ? self::clean(html_entity_decode($item['description'])) : null,
                    'registration_url' => is_array($offer) && is_string($offer['url'] ?? null) ? $offer['url'] : null,
                ];
            }
        }

        return $result;
    }

    /**
     * Sarlavhada kalit so'zlardan biri bormi. Katta-kichik harf, defis va
     * bo'shliqlar farqi hisobga olinmaydi. Mos kelgan birinchi kalit so'zni qaytaradi.
     *
     * @param  list<string>  $keywords
     */
    public static function matchKeyword(string $title, array $keywords): ?string
    {
        $haystack = self::normalizeForMatch($title);

        foreach ($keywords as $keyword) {
            $needle = self::normalizeForMatch($keyword);

            if ($needle !== '' && str_contains($haystack, $needle)) {
                return $keyword;
            }
        }

        return null;
    }

    private static function normalizeForMatch(string $text): string
    {
        $text = mb_strtolower(self::clean($text));
        $text = preg_replace('/[\-‐‑‒–—]+/u', ' ', $text);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    private static function parseIsoDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    /** Elementning faqat o'z matni (ichidagi teglarsiz): sana span'idan kategoriya linkini chiqarib tashlash uchun */
    private static function ownText(DOMNode $node): string
    {
        $text = '';

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text .= $child->textContent;
            }
        }

        return self::clean($text);
    }

    private static function optionalText(Crawler $box, string $selector): ?string
    {
        $node = $box->filter($selector);
        $text = $node->count() ? self::clean($node->text()) : '';

        return $text !== '' ? $text : null;
    }

    private static function absoluteUrl(string $href): string
    {
        return str_starts_with($href, 'http') ? $href : self::BASE_URL . '/' . ltrim($href, '/');
    }

    private static function clean(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $text)));
    }
}
