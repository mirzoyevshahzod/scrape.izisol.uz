<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * info.mintrans.uz'dan avtoraqam bo'yicha litsenziya ma'lumotlarini oladi.
 *
 * Sayt ("Qidirish" tugmasi) bitta oddiy JSON POST yuboradi — cookie, token,
 * captcha kerak emas, shuning uchun Selenium/shell o'rniga Laravel Http client.
 *
 *   POST /api0/check-data-by-search  {"autoN": "10S208NB"}
 *   -> {"data": null}                     topilmadi
 *   -> {"data": "<html>...", "pdfUrl": …}  topildi (HTML hujjat)
 */
class MintransClient
{
    public const URL = 'https://info.mintrans.uz/api0/check-data-by-search';

    /** API tekshiruvi uchun mavjud raqam (yuk mashinasi, MAN TGX) */
    public const PING_NUMBER = '10S208NB';

    /**
     * @return array<int,array<string,string>> har bir litsenziya — alohida qator; topilmasa bo'sh massiv
     *
     * @throws RequestException|ConnectionException tarmoq yoki server xatosi (3 marta urinishdan keyin)
     */
    public function search(string $autoNumber): array
    {
        $autoNumber = strtoupper(preg_replace('/[\s-]+/', '', $autoNumber));

        $response = $this->request()->post(self::URL, ['autoN' => $autoNumber]);
        $response->throw();

        $html = $response->json('data');

        if (! is_string($html) || trim($html) === '') {
            return [];
        }

        $rows = $this->parse($html);

        if ($rows === []) {
            Log::warning('Mintrans HTML format changed', ['autoN' => $autoNumber, 'html' => mb_substr($html, 0, 500)]);
        }

        return $rows;
    }

    /**
     * "API'ni tekshirish" tugmasi uchun: so'rov o'tadimi va javob kutilgan formatdami.
     *
     * @return array{ok:bool,ms:int,message:string}
     */
    public function ping(): array
    {
        $started = microtime(true);

        try {
            $response = Http::timeout(15)->connectTimeout(8)
                ->acceptJson()->withHeaders($this->headers())
                ->post(self::URL, ['autoN' => self::PING_NUMBER]);

            $ms = (int) round((microtime(true) - $started) * 1000);

            if (! $response->successful()) {
                return ['ok' => false, 'ms' => $ms, 'message' => "Server HTTP {$response->status()} qaytardi."];
            }

            if (! is_array($response->json()) || ! array_key_exists('data', $response->json())) {
                return ['ok' => false, 'ms' => $ms, 'message' => 'Javob formati o\'zgargan (data maydoni yo\'q).'];
            }

            $html = $response->json('data');

            if (is_string($html) && $this->parse($html) === []) {
                return ['ok' => false, 'ms' => $ms, 'message' => 'Javob keldi, lekin HTML tuzilishi o\'zgargan — ma\'lumotni ajratib bo\'lmadi.'];
            }

            return ['ok' => true, 'ms' => $ms, 'message' => 'API ishlayapti.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'ms' => (int) round((microtime(true) - $started) * 1000), 'message' => 'Ulanib bo\'lmadi: ' . $e->getMessage()];
        }
    }

    /** @return array<int,array<string,string>> */
    public function parse(string $html): array
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        // UTF-8 ni to'g'ri o'qishi uchun
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xp = new \DOMXPath($dom);

        // h4: "Rusumi: X Yuk ko'tarish qobiliyati: Y"  yoki  "Rusumi: X O'rindiqlar soni: Y"
        $h4 = $this->clean($xp->query('//h4')->item(0)?->textContent ?? '');
        $rusumi = $yuk = $orindiq = '';

        if (preg_match('/Rusumi:\s*(.*?)\s*(?:Yuk ko.tarish qobiliyati|O.rindiqlar soni):/u', $h4, $m)) {
            $rusumi = trim($m[1]);
        } elseif (preg_match('/Rusumi:\s*(.*)$/u', $h4, $m)) {
            $rusumi = trim($m[1]);
        }

        if (preg_match('/Yuk ko.tarish qobiliyati:\s*([\d.,]+)/u', $h4, $m)) {
            $yuk = $m[1];
        }

        if (preg_match('/O.rindiqlar soni:\s*([\d.,]+)/u', $h4, $m)) {
            $orindiq = $m[1];
        }

        $rows = [];

        foreach ($xp->query('//table//tbody/tr') as $tr) {
            $cell = fn (string $title) => $xp->query('./td[@title="' . $title . '"]', $tr)->item(0);

            $korxonaTd = $cell('Korxona nomi');
            $tooltip = $korxonaTd ? $this->clean($xp->query('.//em', $korxonaTd)->item(0)?->textContent ?? '') : '';

            $holatiTd = $cell('Holati');
            $holati = $this->text($holatiTd);

            // sayt muddati o'tgan/bekor qilingan holatni ustidan chizib ko'rsatadi
            if ($holatiTd && $xp->query('.//s|.//del|.//strike', $holatiTd)->length > 0 && $holati !== '') {
                $holati .= ' (ustidan chizilgan)';
            }

            $rows[] = [
                'rusumi' => $rusumi,
                'yuk' => $yuk,
                'orindiq' => $orindiq,
                'litsenziya' => $this->text($cell('Litsenziya varaqasi')),
                'raqam' => $this->text($cell('Davlat raqami')),
                'korxona' => $this->text($korxonaTd),
                'telefon' => $this->extractPhone($tooltip),
                'manzil' => $tooltip,
                'faoliyat' => $this->text($cell('Faoliyat turi')),
                'transport' => $this->text($cell('Transport turi')),
                'yuk_turi' => $this->text($cell('Yuk turi')),
                'berilgan' => $this->text($cell('Berilgan sana')),
                'muddati' => $this->text($cell('Amal qilish muddati')),
                'holati' => $holati,
                'boshqarma' => $this->text($cell('Hududiy boshqarma')),
            ];
        }

        return $rows;
    }

    private function request(): PendingRequest
    {
        return Http::timeout(20)
            ->connectTimeout(10)
            ->retry(3, 1000, fn ($e) => $e instanceof ConnectionException
                || ($e instanceof RequestException && $e->response->serverError()))
            ->acceptJson()
            ->withHeaders($this->headers());
    }

    private function headers(): array
    {
        return [
            'X-AUTH-TOKEN' => '',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36',
            'Referer' => 'https://info.mintrans.uz/',
            'Origin' => 'https://info.mintrans.uz',
        ];
    }

    /** Katak matni, <em> tooltipsiz */
    private function text(?\DOMNode $td): string
    {
        if (! $td) {
            return '';
        }

        $clone = $td->cloneNode(true);

        foreach (iterator_to_array($clone->getElementsByTagName('em')) as $em) {
            $em->parentNode->removeChild($em);
        }

        return $this->clean($clone->textContent);
    }

    private function clean(string $s): string
    {
        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    private function extractPhone(string $s): string
    {
        $patterns = [
            '/\+998[\s\-]?\d{2}[\s\-]?\d{3}[\s\-]?\d{2}[\s\-]?\d{2}/',
            '/(?<!\d)998\d{9}(?!\d)/',
            '/(?<!\d)\d{2}[\s\-]\d{3}[\s\-]\d{2}[\s\-]\d{2}(?!\d)/',
        ];

        foreach ($patterns as $p) {
            if (preg_match($p, $s, $m)) {
                return trim($m[0]);
            }
        }

        return ''; // server "*********" bilan yashirgan
    }
}
