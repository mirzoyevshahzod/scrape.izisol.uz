<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;
use Throwable;

/**
 * EGS CRM integratsiya API'si (crm.zanjeer.uz/api/v1/integration).
 *
 * Avval CRM bilan Selenium (headless Chrome) orqali ishlanardi; endi
 * hammasi shu API orqali: granitsalar, "Торнадо" importi, buyurtmalar
 * (mashina raqami bo'yicha menejer) va kontragentlar (kompaniya bo'yicha
 * mas'ul xodim). Kalit: EGS_CRM_API_KEY (.env).
 *
 * API daqiqasiga 120 so'rov qabul qiladi — so'rovlar orasida kamida 0.5 s
 * kutiladi, 429 kelsa Retry-After bo'yicha kutib qayta uriniladi.
 */
class EgsCrmClient
{
    /** 120 so'rov/daqiqa */
    private const MIN_INTERVAL_MS = 500;

    private const PER_PAGE = 100;

    private string $baseUrl;

    private ?string $apiKey;

    private float $lastRequestAt = 0;

    /** @var array<int,array{id:int,name:string,country_from:?string,country_to:?string}>|null */
    private ?array $borders = null;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('crm.egs.base_url'), '/');
        $this->apiKey = config('crm.egs.api_key') ?: null;
    }

    /** @return array<int,array{id:int,name:string,country_from:?string,country_to:?string}> */
    public function borders(): array
    {
        return $this->borders ??= $this->all('borders');
    }

    /** Granitsa nomi bo'yicha ID (tire turi va bo'sh joylar farqi hisobga olinmaydi) */
    public function borderId(string $name): ?int
    {
        $wanted = self::normalizeBorder($name);

        foreach ($this->borders() as $border) {
            if (self::normalizeBorder($border['name']) === $wanted) {
                return (int) $border['id'];
            }
        }

        return null;
    }

    /**
     * "Торнадо" (custom-operations) importi. Excel'da "Avtomobil raqami" va
     * "Sana" ustunlari. Qayta yuklash xavfsiz — mavjud yozuvlar yangilanadi.
     *
     * @return int yuklangan qatorlar soni
     */
    public function importOperations(int $borderId, string $file): int
    {
        $response = $this->send(fn (PendingRequest $request) => $request
            ->timeout(300)
            ->attach('file', file_get_contents($file), basename($file))
            ->post('custom-operations/import', ['border_id' => $borderId]));

        return (int) ($response->json('data.rows_count') ?? 0);
    }

    /**
     * Qidiruvga mos barcha sub-buyurtmalar (hamma sahifalar).
     *
     * @return array<int,array<string,mixed>>
     */
    public function orders(string $search): array
    {
        return $this->all('orders', ['search' => $search]);
    }

    /**
     * Qidiruvga mos kontragentlar (birinchi sahifa).
     *
     * @return array<int,array<string,mixed>>
     */
    public function contragents(string $search, array $filters = []): array
    {
        return $this->get('contragents', ['search' => $search, 'per_page' => 20] + $filters)['data'] ?? [];
    }

    /** @return array{ok:bool,ms:int,message:string} */
    public function ping(): array
    {
        $started = microtime(true);

        try {
            $total = (int) ($this->get('borders', ['per_page' => 1])['total'] ?? 0);

            return ['ok' => true, 'ms' => (int) ((microtime(true) - $started) * 1000), 'message' => "API ishlayapti (granitsalar: {$total} ta)"];
        } catch (Throwable $e) {
            return ['ok' => false, 'ms' => (int) ((microtime(true) - $started) * 1000), 'message' => $e->getMessage()];
        }
    }

    /**
     * Sahifali ro'yxatning `data` qismi (data.data, total, last_page ...).
     */
    public function get(string $path, array $query = []): array
    {
        return (array) $this->send(fn (PendingRequest $request) => $request->get($path, $query))->json('data');
    }

    /**
     * Ro'yxatning barcha sahifalaridagi yozuvlar.
     *
     * @return array<int,array<string,mixed>>
     */
    public function all(string $path, array $query = []): array
    {
        $items = [];
        $page = 1;

        do {
            $data = $this->get($path, ['per_page' => self::PER_PAGE, 'page' => $page] + $query);
            array_push($items, ...($data['data'] ?? []));
            $page++;
        } while ($page <= (int) ($data['last_page'] ?? 1));

        return $items;
    }

    public static function normalizeBorder(string $name): string
    {
        $name = preg_replace('/\s*[-–—]\s*/u', ' - ', mb_strtolower(trim($name)));

        return preg_replace('/\s+/u', ' ', $name);
    }

    /**
     * @param  callable(PendingRequest):Response  $call
     *
     * @throws RuntimeException
     */
    private function send(callable $call): Response
    {
        if (! $this->apiKey) {
            throw new EgsCrmAuthException('EGS_CRM_API_KEY .env faylida berilmagan.');
        }

        $this->throttle();

        try {
            $response = $call($this->request());
        } catch (ConnectionException $e) {
            throw new RuntimeException('CRM API\'ga ulanib bo\'lmadi: ' . $e->getMessage(), 0, $e);
        }

        if ($response->successful()) {
            return $response;
        }

        if ($response->status() === 401) {
            throw new EgsCrmAuthException('CRM API kaliti noto\'g\'ri (401). EGS_CRM_API_KEY\'ni tekshiring.');
        }

        throw new RuntimeException(match (true) {
            $response->status() === 422 => 'CRM qabul qilmadi (422): ' . self::validationMessage($response),
            default => "CRM API javobi HTTP {$response->status()}: " . mb_substr(trim(strip_tags($response->body())), 0, 200),
        });
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['X-Api-Key' => $this->apiKey, 'Accept' => 'application/json'])
            ->timeout(60)
            ->connectTimeout(10)
            ->retry(
                3,
                // 429 — API aytgan vaqtcha (bo'lmasa 30 s), boshqa xatolarda 2 s
                fn (int $attempt, Throwable $e) => $e instanceof RequestException && $e->response->status() === 429
                    ? max(1, (int) $e->response->header('Retry-After') ?: 30) * 1000
                    : 2000,
                fn (Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && ($e->response->serverError() || $e->response->status() === 429)),
                throw: false
            );
    }

    private function throttle(): void
    {
        $waitMs = self::MIN_INTERVAL_MS - (microtime(true) - $this->lastRequestAt) * 1000;

        if ($this->lastRequestAt > 0 && $waitMs > 0) {
            Sleep::usleep((int) ($waitMs * 1000));
        }

        $this->lastRequestAt = microtime(true);
    }

    private static function validationMessage(Response $response): string
    {
        $errors = collect($response->json('errors') ?? [])->flatten()->filter()->take(3)->implode('; ');

        return trim(($response->json('message') ?? '') . ($errors ? " ({$errors})" : '')) ?: mb_substr($response->body(), 0, 200);
    }
}
