<?php

namespace Tests\Feature;

use App\Models\OrginfoInn;
use App\Services\OrginfoClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class OrginfoClientTest extends TestCase
{
    use RefreshDatabase;

    /**
     * orginfo.uz qidiruv sahifasi (tuzilishi haqiqiy sahifadan): har bir bo'lim h4,
     * natijalar a.og-card. $orgs: [inn, nom, tugatilganmi?]; $founders — "Учредители" bo'limi.
     */
    public static function page(array $orgs, array $founders = []): string
    {
        $card = fn ($inn, $name, $liquidated = false) => '<a href="/ru/organization/x/" class="text-decoration-none og-card" role="button">'
            . '<div class="card"><div class="d-flex"><p><span class="bg-success bg-opacity-25 text-success px-2">' . $inn . '</span></p>'
            . '<p>' . ($liquidated ? '<span class="bg-danger bg-opacity-25 text-danger">Ликвидирована</span>' : '') . '<span>20.04.2021</span></p></div>'
            . '<div class="card-body"><h6 class="card-title">' . htmlspecialchars($name) . '</h6><p>Toshkent</p></div></div></a>';

        return '<html><body><h1>Результаты найдены по запросу</h1>'
            . '<div class="filter-field"><h4>Организации</h4></div><div class="py-3">' . implode('', array_map(fn ($o) => $card(...$o), $orgs)) . '</div>'
            . '<div class="filter-field"><h4>Учредители</h4></div><div class="py-3">' . implode('', array_map(fn ($o) => $card(...$o), $founders)) . '</div>'
            . '<div class="filter-field"><h4>ИП</h4></div><div class="py-3"><h6>Ничего не найдено</h6></div>'
            . '</body></html>';
    }

    public function test_takes_inn_only_from_active_exactly_matching_organization(): void
    {
        $html = self::page(
            [
                ['202114530', '"BAXT TRANSPORT" хусусий фирмаси', true],                     // tugatilgan
                ['111111111', '"BAXT TRANSPORT SERVIS" mas`uliyati cheklangan jamiyati'],   // nom mos emas
                ['308413072', '"BAXT TRANSPORT" mas`uliyati cheklangan jamiyati'],           // mos
                ['308413073', 'BAXT TRANSPORT MCHJ'],                                        // mos, lekin ikkinchi
            ],
            founders: [['999999999', '"BAXT TRANSPORT" MCHJ']],
        );

        $this->assertSame('308413072', OrginfoClient::pickInn($html, 'OOO "BAXT TRANSPORT"'));
        $this->assertCount(4, OrginfoClient::organizations($html)); // "Учредители" kartalari hisobga olinmaydi
    }

    public function test_no_match_gives_null(): void
    {
        $this->assertNull(OrginfoClient::pickInn(self::page([['206740272', '"ТРАНС-СЕРВИС" хусусий корхонаси', true]]), 'OOO "TRANS"'));
        $this->assertNull(OrginfoClient::pickInn(self::page([], founders: [['1', '"TRANS" MCHJ']]), 'OOO "TRANS"'));
        $this->assertNull(OrginfoClient::pickInn(self::page([]), 'OOO "ANAS TRANS SERVICE"'));
    }

    public function test_masked_name_matches_by_first_letters_and_lengths(): void
    {
        $html = self::page([
            ['202397124', '"М Х" хусусий фирмаси'],
            ['305000001', '"XOLMATOVA MAXFUZA" MCHJ'],
        ]);

        $this->assertSame('305000001', OrginfoClient::pickInn($html, 'OOO "X******** M******"'));
        $this->assertNull(OrginfoClient::pickInn($html, 'OOO "X********* M*******"')); // uzunligi mos emas
    }

    public function test_find_inn_queries_inner_name(): void
    {
        Http::fake(['orginfo.uz/*' => Http::response(self::page([['308413072', 'BAXT TRANSPORT MCHJ']]))]);

        $this->assertSame('308413072', (new OrginfoClient())->findInn('OOO "BAXT TRANSPORT"'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'q=BAXT+TRANSPORT') || str_contains($request->url(), 'q=BAXT%20TRANSPORT'));
    }

    public function test_inn_command_fills_template_and_uses_cache(): void
    {
        Sleep::fake();
        Http::fake(function ($request) {
            return str_contains(urldecode($request->url()), 'BAXT TRANSPORT')
                ? Http::response(self::page([['308413072', '"BAXT TRANSPORT" MCHJ']]))
                : Http::response(self::page([]));
        });

        $path = sys_get_temp_dir() . '/inn-' . uniqid() . '.xlsx';
        $book = new Spreadsheet();
        $book->getActiveSheet()->fromArray([
            ['Rusumi', "Yuk ko'tarish qobiliyati", 'Litsenziya varaqasi', 'Davlat raqami', 'Korxona nomi'],
            ['MAN', 0, 'AT 1', '10S208NB', 'OOO "BAXT TRANSPORT"'],
            ['DAF', 0, 'AT 2', '01A222MC', 'OOO "ANAS TRANS SERVICE"'],
            ['VOLVO', 0, 'AT 3', '40H916QB', 'OOO "BAXT TRANSPORT"'],
        ]);
        (new Xlsx($book))->save($path);

        $this->artisan('mintrans:inn', ['file' => $path])
            ->expectsOutputToContain('Noyob korxona nomlari: 2 ta (3 qator)')
            ->expectsOutputToContain('✅ [1/2] BAXT TRANSPORT — INN 308413072')
            ->expectsOutputToContain('❌ [2/2] ANAS TRANS SERVICE — INN topilmadi')
            ->assertSuccessful();

        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = $sheet->toArray(null, false, false, true);
        $this->assertSame(['инн', 'Yangi telefon raqami'], [$rows[1]['N'], $rows[1]['O']]);
        $this->assertSame(['308413072', null, '308413072'], [$rows[2]['N'], $rows[3]['N'], $rows[4]['N']]);
        $this->assertSame('s', $sheet->getCell('N2')->getDataType()); // INN matn sifatida
        $this->assertFalse($sheet->getStyle('A1')->getFont()->getBold()); // shablondagidek
        Http::assertSentCount(2);

        // qayta ishga tushirish — orginfo'ga murojaat qilinmaydi, N ustuni takrorlanmaydi
        $this->artisan('mintrans:inn', ['file' => $path])->expectsOutputToContain('bazadan olindi: 2')->assertSuccessful();
        Http::assertSentCount(2);
        $this->assertSame('O', IOFactory::load($path)->getActiveSheet()->getHighestColumn());

        // topilmagani 7 kundan keyin qayta qidiriladi
        OrginfoInn::where('name', 'OOO "ANAS TRANS SERVICE"')->update(['checked_at' => now()->subDays(8)]);
        $this->artisan('mintrans:inn', ['file' => $path])->assertSuccessful();
        Http::assertSentCount(3);

        unlink($path);
    }

    public function test_failed_parallel_request_is_retried_alone(): void
    {
        Sleep::fake();
        Http::fakeSequence('orginfo.uz/*')
            ->push('xato', 502)                                                      // parallel so'rov
            ->push(self::page([['308413072', '"BAXT TRANSPORT" MCHJ']]));            // alohida qayta urinish

        $this->assertSame(['OOO "BAXT TRANSPORT"' => ['inn' => '308413072']], (new OrginfoClient())->findInnMany(['OOO "BAXT TRANSPORT"']));
    }

    public function test_request_that_keeps_failing_is_reported_as_error(): void
    {
        Sleep::fake();
        Http::fake(['orginfo.uz/*' => Http::response('xato', 503)]);

        $result = (new OrginfoClient())->findInnMany(['OOO "BAXT TRANSPORT"', 'OOO ""']);

        $this->assertArrayHasKey('error', $result['OOO "BAXT TRANSPORT"']);
        $this->assertSame(['inn' => null], $result['OOO ""']); // bo'sh nom so'ralmaydi
    }
}
