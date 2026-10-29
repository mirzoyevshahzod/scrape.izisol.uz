<?php

namespace App\Services;

use App\Support\CompanyName;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

/**
 * orginfo.uz qidiruvidan korxona INN'ini topadi (3-bosqich).
 *
 * Qidiruv sahifasi bo'limlarga ajratilgan (Организации, Учредители, Руководитель,
 * ИП, ...). INN faqat "Организации" bo'limidan olinadi va faqat:
 *   - tashkilot tugatilmagan ("Ликвидирована" belgisi yo'q);
 *   - nomi bizdagi nom bilan aynan mos (yuridik shakl va qo'shtirnoqlarsiz);
 *     Mintrans yashirgan nomlarda (X********* M*******) — har bir so'zning
 *     birinchi harfi va uzunligi mos.
 * Bir nechta mos kelsa — birinchisi. Orginfo'ning "birinchi natija"si ko'pincha
 * boshqa (hatto tugatilgan) kompaniya bo'ladi, shuning uchun moslik shart.
 */
class OrginfoClient
{
    public const SEARCH_URL = 'https://orginfo.uz/ru/search/all/';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

    /**
     * @param  string  $name  korxona nomi (OOO "NOMI" yoki istalgan ko'rinishda)
     * @return string|null topilgan INN
     *
     * @throws RequestException|ConnectionException
     */
    public function findInn(string $name): ?string
    {
        $query = self::innerName($name);

        if ($query === '') {
            return null;
        }

        $response = Http::timeout(30)
            ->connectTimeout(10)
            ->retry(3, 2000, fn ($e) => $e instanceof ConnectionException
                || ($e instanceof RequestException && $e->response->serverError()))
            ->withHeaders(['User-Agent' => self::USER_AGENT])
            ->get(self::SEARCH_URL, ['q' => $query]);

        $response->throw();

        return self::pickInn($response->body(), $name);
    }

    /**
     * Bir nechta nomni parallel qidiradi (Http::pool). Parallel so'rovi
     * muvaffaqiyatsiz bo'lgan nom alohida, qayta urinishlar bilan so'raladi.
     *
     * @param  array<int,string>  $names
     * @return array<string,array{inn?:?string,error?:string}> nom => ['inn' => ...] yoki ['error' => ...]
     */
    public function findInnMany(array $names): array
    {
        $queries = array_filter(array_combine($names, array_map(fn ($n) => self::innerName($n), $names)), fn ($q) => $q !== '');

        $responses = $queries ? Http::pool(fn (Pool $pool) => array_map(
            fn ($name) => $pool->as((string) $name)
                ->timeout(60)->connectTimeout(10)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get(self::SEARCH_URL, ['q' => $queries[$name]]),
            array_keys($queries)
        )) : [];

        $results = [];

        foreach ($names as $name) {
            if (! isset($queries[$name])) {
                $results[$name] = ['inn' => null];

                continue;
            }

            $response = $responses[$name] ?? null;

            try {
                $results[$name] = ['inn' => $response instanceof Response && $response->successful()
                    ? self::pickInn($response->body(), $name)
                    : $this->findInn($name)];
            } catch (Throwable $e) {
                $results[$name] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /** "Организации" bo'limidan mos keladigan birinchi faol tashkilotning INN'i */
    public static function pickInn(string $html, string $name): ?string
    {
        $wanted = self::innerName($name);

        foreach (self::organizations($html) as $org) {
            if (! $org['liquidated'] && $org['inn'] !== '' && self::matches(self::innerName($org['name']), $wanted)) {
                return $org['inn'];
            }
        }

        return null;
    }

    /** @return array<int,array{inn:string,name:string,liquidated:bool}> */
    public static function organizations(string $html): array
    {
        $crawler = new Crawler($html);

        // kartaning eng yaqin oldingi h4 sarlavhasi "Организации" bo'lsa — u tashkilotlar bo'limida
        return $crawler
            ->filterXPath('//a[contains(concat(" ", normalize-space(@class), " "), " og-card ")][preceding::h4[1][normalize-space()="Организации"]]')
            ->each(fn (Crawler $card) => [
                'inn' => preg_replace('/\D/', '', $card->filter('.bg-success')->count() ? $card->filter('.bg-success')->first()->text('') : ''),
                'name' => trim(preg_replace('/\s+/u', ' ', $card->filter('h6')->count() ? $card->filter('h6')->first()->text('') : '')),
                'liquidated' => $card->filter('.bg-danger')->count() > 0
                    || str_contains(mb_strtolower($card->text('')), 'ликвидирован'),
            ]);
    }

    /** OOO "NOMI" -> NOMI (yuridik shakl, qo'shtirnoqsiz, katta harf) */
    public static function innerName(string $name): string
    {
        $formatted = CompanyName::format($name);

        if (preg_match('/^OOO "(.*)"$/u', $formatted, $m)) {
            return $m[1];
        }

        return mb_strtoupper(trim($formatted));
    }

    private static function matches(string $candidate, string $wanted): bool
    {
        $normalize = fn (string $s) => preg_replace('/\s+/u', ' ', preg_replace('/\s*-\s*/u', '-', trim($s)));
        $candidate = $normalize($candidate);
        $wanted = $normalize($wanted);

        if (! str_contains($wanted, '*')) {
            return $candidate === $wanted;
        }

        // yashirilgan nom: so'zlar soni, har bir so'zning birinchi harfi va uzunligi
        $a = explode(' ', $candidate);
        $b = explode(' ', $wanted);

        if (count($a) !== count($b)) {
            return false;
        }

        foreach ($b as $i => $word) {
            if (! str_contains($word, '*')) {
                if ($word !== $a[$i]) {
                    return false;
                }

                continue;
            }

            if (mb_strlen($word) !== mb_strlen($a[$i]) || mb_substr($word, 0, 1) !== mb_substr($a[$i], 0, 1)) {
                return false;
            }
        }

        return true;
    }
}
