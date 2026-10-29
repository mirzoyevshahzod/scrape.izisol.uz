<?php

namespace Tests\Feature;

use App\Services\MintransClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class MintransClientTest extends TestCase
{
    /** info.mintrans.uz javobidagi data HTML'i (tuzilishi haqiqiy javobdan) */
    public static function html(string $h4, array $rows): string
    {
        $tr = '';
        foreach ($rows as $r) {
            $tr .= '<tr>'
                . '<td title="№">1</td>'
                . '<td title="Litsenziya varaqasi"><span class="tooltip2"><em>09.07.2027</em>' . $r['license'] . '</span></td>'
                . '<td title="Davlat raqami">' . $r['plate'] . '</td>'
                . '<td title="Korxona nomi"><span class="tooltip2"><em>' . ($r['tooltip'] ?? 'Узбекистан, Ташкент, ************') . '</em>' . $r['company'] . '</td>'
                . '<td title="Faoliyat turi"><span class="tooltip2"><em>МнПГ</em>Международные перевозки грузов</span></td>'
                . '<td title="Transport turi">' . ($r['transport'] ?? 'Грузовой') . '</td>'
                . '<td title="Yuk turi">Тягач (седельный тягач)</td>'
                . '<td title="Berilgan sana">10.07.2026</td>'
                . '<td title="Amal qilish muddati">09.07.2027</td>'
                . '<td title="Holati">' . ($r['status'] ?? '<b>Действующий</b>') . '</td>'
                . '<td title="Hududiy boshqarma">Центральный аппарат</td>'
                . '</tr>';
        }

        return '<html id="data_report"><body><h4 class="text-center center">' . $h4 . '</h4>'
            . '<table class="table"><thead><tr><th>№</th></tr></thead><tbody>' . $tr . '</tbody></table></body></html>';
    }

    public function test_parses_truck_and_strips_tooltips(): void
    {
        $rows = (new MintransClient())->parse(self::html("Rusumi: MAN TGX 18 420 Yuk ko'tarish qobiliyati: 0.00 ", [
            ['license' => 'AT 10437261', 'plate' => '10S208NB', 'company' => 'МЧЖ "BAXT TRANSPORT"'],
        ]));

        $this->assertCount(1, $rows);
        $this->assertSame('MAN TGX 18 420', $rows[0]['rusumi']);
        $this->assertSame('0.00', $rows[0]['yuk']);
        $this->assertSame('', $rows[0]['orindiq']);
        $this->assertSame('AT 10437261', $rows[0]['litsenziya']); // <em>09.07.2027</em> yopishmagan
        $this->assertSame('10S208NB', $rows[0]['raqam']);
        $this->assertSame('МЧЖ "BAXT TRANSPORT"', $rows[0]['korxona']);
        $this->assertSame('Международные перевозки грузов', $rows[0]['faoliyat']); // "МнПГ" yopishmagan
        $this->assertSame('Действующий', $rows[0]['holati']);
        $this->assertSame('', $rows[0]['telefon']); // ****** — yashirilgan
    }

    public function test_parses_passenger_car_heading(): void
    {
        $rows = (new MintransClient())->parse(self::html("Rusumi: LACETTI O'rindiqlar soni: 4.00", [
            ['license' => 'AT 10099231', 'plate' => '01Z001AA', 'company' => "O'O'B T*******", 'transport' => 'Легковой'],
        ]));

        $this->assertSame('LACETTI', $rows[0]['rusumi']);
        $this->assertSame('', $rows[0]['yuk']);
        $this->assertSame('4.00', $rows[0]['orindiq']);
    }

    public function test_every_license_is_a_row_and_real_phone_is_kept(): void
    {
        $rows = (new MintransClient())->parse(self::html('Rusumi: DAF', [
            ['license' => 'AT 1', 'plate' => '01A222MC', 'company' => 'A', 'tooltip' => 'Toshkent, +998 90 123 45 67'],
            ['license' => 'AT 2', 'plate' => '01A222MC', 'company' => 'B', 'status' => '<s><b>Действующий</b></s>'],
        ]));

        $this->assertCount(2, $rows);
        $this->assertSame('+998 90 123 45 67', $rows[0]['telefon']);
        $this->assertSame('Действующий (ustidan chizilgan)', $rows[1]['holati']);
    }

    public function test_search_returns_empty_when_not_found(): void
    {
        Http::fake(['info.mintrans.uz/*' => Http::response(['data' => null, 'pdfUrl' => null])]);

        $this->assertSame([], (new MintransClient())->search('01 z 001 zz'));

        Http::assertSent(fn ($request) => $request['autoN'] === '01Z001ZZ' && $request->hasHeader('X-AUTH-TOKEN'));
    }

    public function test_search_retries_server_errors_then_throws(): void
    {
        Sleep::fake();
        Http::fake(['info.mintrans.uz/*' => Http::response('down', 500)]);

        try {
            (new MintransClient())->search('10S208NB');
            $this->fail('Xato kutilgan edi');
        } catch (RequestException $e) {
            $this->assertSame(500, $e->response->status());
        }

        Http::assertSentCount(3);
    }

    public function test_ping_ok(): void
    {
        Http::fake(['info.mintrans.uz/*' => Http::response(['data' => self::html('Rusumi: MAN', [['license' => 'AT 1', 'plate' => '10S208NB', 'company' => 'X']])])]);

        $this->assertTrue((new MintransClient())->ping()['ok']);
    }

    public function test_ping_server_error(): void
    {
        Http::fake(['info.mintrans.uz/*' => Http::response('down', 503)]);
        $result = (new MintransClient())->ping();
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('503', $result['message']);
    }

    public function test_ping_detects_changed_html(): void
    {
        Http::fake(['info.mintrans.uz/*' => Http::response(['data' => '<html><body><p>yangi dizayn</p></body></html>'])]);

        $result = (new MintransClient())->ping();
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('tuzilishi', $result['message']);
    }
}
