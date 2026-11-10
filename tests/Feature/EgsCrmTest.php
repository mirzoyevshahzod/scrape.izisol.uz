<?php

namespace Tests\Feature;

use App\Console\Commands\ScrapeZanjeerOperators;
use App\Services\EgsCrmAuthException;
use App\Services\EgsCrmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Tests\TestCase;

class EgsCrmTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://crm.test/api/v1/integration';

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        config(['crm.egs.base_url' => self::API, 'crm.egs.api_key' => 'test-key']);
        Sleep::fake();
        Http::preventStrayRequests();

        $this->dir = sys_get_temp_dir() . '/egs-test-' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("{$this->dir}/*"));
        rmdir($this->dir);

        parent::tearDown();
    }

    private static function page(array $items, int $page = 1, int $lastPage = 1): array
    {
        return ['success' => true, 'message' => null, 'data' => [
            'current_page' => $page, 'last_page' => $lastPage, 'total' => count($items), 'data' => $items,
        ]];
    }

    private static function borders(): array
    {
        return self::page([
            ['id' => 2, 'name' => 'Хопа-Сарпи', 'country_from' => 'Турция', 'country_to' => 'Грузия'],
            ['id' => 15, 'name' => 'Привалка -  Райгардас', 'country_from' => 'Беларусь', 'country_to' => 'Литва'],
        ]);
    }

    private static function order(int $id, int $parent, string $auto, string $operation): array
    {
        return ['id' => $id, 'parent_order_id' => $parent, 'vehicle' => ['auto_number' => $auto, 'semi_trailer_number' => null],
            'operation' => ['id' => 1, 'name' => $operation]];
    }

    private function excel(array $rows): string
    {
        $book = new Spreadsheet();
        $book->getActiveSheet()->fromArray($rows);
        $path = "{$this->dir}/" . uniqid() . '.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }

    // --- klient ---

    public function test_client_sends_key_reads_all_pages_and_matches_border_names(): void
    {
        Http::fake([
            self::API . '/borders*' => fn (Request $r) => $r['page'] == 1
                ? Http::response(['success' => true, 'data' => ['last_page' => 2, 'data' => [['id' => 2, 'name' => 'Хопа-Сарпи']]]])
                : Http::response(['success' => true, 'data' => ['last_page' => 2, 'data' => [['id' => 15, 'name' => 'Привалка -  Райгардас']]]]),
        ]);

        $crm = new EgsCrmClient();

        $this->assertSame(2, $crm->borderId('Хопа - Сарпи'));
        $this->assertSame(15, $crm->borderId('Привалка — Райгардас'));
        $this->assertNull($crm->borderId('Владикавказ — Казбеги'));

        Http::assertSentCount(2); // ro'yxat bir marta olinadi
        Http::assertSent(fn (Request $r) => $r->hasHeader('X-Api-Key', 'test-key') && $r->hasHeader('Accept', 'application/json') && $r['per_page'] == 100);
    }

    public function test_client_errors(): void
    {
        Http::fake([
            self::API . '/orders*' => Http::response(['message' => 'Unauthenticated.'], 401),
            self::API . '/custom-operations/import' => Http::response(['message' => 'Ошибка', 'errors' => ['file' => ['Строка 3: неверная дата']]], 422),
        ]);
        $crm = new EgsCrmClient();

        try {
            $crm->orders('01A123BC');
            $this->fail('401 kutilgan edi');
        } catch (EgsCrmAuthException $e) {
            $this->assertStringContainsString('401', $e->getMessage());
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CRM qabul qilmadi (422): Ошибка (Строка 3: неверная дата)');
        $crm->importOperations(2, $this->excel([['Avtomobil raqami', 'Sana']]));
    }

    public function test_missing_key_is_reported_without_request(): void
    {
        config(['crm.egs.api_key' => null]);

        $this->expectException(EgsCrmAuthException::class);
        (new EgsCrmClient())->borders();
    }

    // --- import ---

    public function test_import_command_resolves_border_from_file_name_and_uploads(): void
    {
        Http::fake([
            self::API . '/borders*' => Http::response(self::borders()),
            self::API . '/custom-operations/import' => Http::response(['success' => true, 'message' => 'Успешно сохранено', 'data' => ['rows_count' => 152]]),
        ]);
        $file = "{$this->dir}/Хопа-Сарпи.xlsx";
        rename($this->excel([['Avtomobil raqami', 'Sana'], ['01A123BC', '29/10/2026']]), $file);

        $this->artisan('crm:import-custom-operations', ['path' => $file])
            ->expectsOutputToContain("'Хопа-Сарпи' uchun fayl import qilindi: 152 ta qator.")
            ->assertSuccessful();

        Http::assertSent(fn (Request $r) => $r->url() === self::API . '/custom-operations/import'
            && $r->isMultipart()
            && collect($r->data())->contains(fn ($part) => $part['name'] === 'border_id' && $part['contents'] == 2)
            && collect($r->data())->contains(fn ($part) => $part['name'] === 'file' && $part['filename'] === 'Хопа-Сарпи.xlsx'));
    }

    public function test_import_command_unknown_border_lists_available(): void
    {
        Http::fake([self::API . '/borders*' => Http::response(self::borders())]);

        $this->artisan('crm:import-custom-operations', ['path' => $this->excel([['Avtomobil raqami', 'Sana']]), '--border' => 'Владикавказ — Казбеги'])
            ->expectsOutputToContain("'Владикавказ — Казбеги' granitsasi CRM'da topilmadi. Mavjudlari: Хопа-Сарпи, Привалка -  Райгардас")
            ->assertFailed();

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'custom-operations'));
    }

    public function test_daily_import_stops_when_api_key_is_wrong(): void
    {
        Http::fake([self::API . '/*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

        $this->artisan('crm:daily-import')
            ->expectsOutputToContain('CRM API ishlamayapti: CRM API kaliti noto\'g\'ri (401)')
            ->assertFailed();
    }

    public function test_ping_lists_borders(): void
    {
        Http::fake([self::API . '/borders*' => Http::response(self::borders())]);

        $this->artisan('crm:ping')
            ->expectsOutputToContain('API ishlayapti (granitsalar: 2 ta)')
            ->assertSuccessful();
    }

    // --- menejerlar ---

    public function test_plate_operators_counts_each_parent_order_once(): void
    {
        Http::fake([self::API . '/orders*' => fn (Request $r) => Http::response(self::page(match ($r['search']) {
            // 100: ALI (3 ta sub-buyurtma — bir marta), 101 va 102: VALI; 103 — boshqa mashina (yuk nomida raqam)
            '01A123BC' => [
                self::order(1, 100, '01 A 123 BC', 'ALI'), self::order(2, 100, '01A123BC', 'ALI'), self::order(3, 100, '01A123BC', 'ALI'),
                self::order(4, 101, '01A123BC', 'VALI'), self::order(5, 102, '01A123BC', 'VALI'),
                self::order(6, 103, '40176BBB', 'ALI'), self::order(7, 104, '40176BBB', 'ALI'),
            ],
            default => [],
        }))]);
        $file = $this->excel([['Mashina raqami', 'Company'], ['01A123BC', 'OOO "BETA"'], ['90Z999ZZ', 'OOO "ALFA"']]);

        $this->artisan('crm:plate-operators', ['file' => $file])
            ->expectsOutputToContain('01A123BC => VALI')
            ->expectsOutputToContain('90Z999ZZ => topilmadi')
            ->assertSuccessful();

        $rows = IOFactory::load($file)->getActiveSheet()->toArray();
        $this->assertSame(['Mashina raqami', 'Company', 'Manager'], $rows[0]);
        $this->assertSame(['90Z999ZZ', 'OOO "ALFA"', 'topilmadi'], $rows[1]); // kompaniya bo'yicha saralangan
        $this->assertSame(['01A123BC', 'OOO "BETA"', 'VALI'], $rows[2]);
    }

    public function test_plate_operators_stops_asking_after_auth_error(): void
    {
        Http::fake([self::API . '/orders*' => Http::response(['message' => 'Unauthenticated.'], 401)]);
        $file = $this->excel([['Mashina raqami'], ['01A123BC'], ['40176BBB']]);

        $this->artisan('crm:plate-operators', ['file' => $file])->assertFailed();

        Http::assertSentCount(1);
        $this->assertSame([['Mashina raqami', 'Manager'], ['01A123BC', null], ['40176BBB', null]], IOFactory::load($file)->getActiveSheet()->toArray(null, false)); // Manager bo'sh
    }

    public function test_company_operator_prefers_exact_name_match(): void
    {
        $contragents = [
            ['company_name' => 'BAXT TRANSPORT SERVIS', 'name' => '', 'legal_name' => '', 'employee' => ['name' => 'BOSHQA ODAM']],
            ['company_name' => 'ООО "BAXT TRANSPORT"', 'name' => '', 'legal_name' => '', 'employee' => ['name' => 'KARIMOV ANVAR']],
        ];

        $this->assertSame('BAXT TRANSPORT', ScrapeZanjeerOperators::searchName('OOO "BAXT  TRANSPORT"'));
        $this->assertSame('KARIMOV ANVAR', ScrapeZanjeerOperators::pickOperator($contragents, 'OOO "BAXT TRANSPORT"'));
        $this->assertSame('BOSHQA ODAM', ScrapeZanjeerOperators::pickOperator($contragents, 'OOO "BAXT"')); // aniq mosi yo'q — birinchisi
        $this->assertSame('-', ScrapeZanjeerOperators::pickOperator([['company_name' => 'X', 'employee' => ['name' => 'EGS OPERATION']]], 'X'));
        $this->assertSame('-', ScrapeZanjeerOperators::pickOperator([['company_name' => 'X', 'employee' => null]], 'X'));
        $this->assertSame('-', ScrapeZanjeerOperators::pickOperator([], 'X'));
    }

    public function test_company_manager_and_inn_come_from_orders(): void
    {
        $order = fn (int $id, int $parent, ?array $carrier, ?array $customer, string $operation) => ['id' => $id, 'parent_order_id' => $parent,
            'carrier' => $carrier, 'customer' => $customer, 'operation' => ['name' => $operation], 'vehicle' => null];
        $baxt = ['id' => 'C-1', 'name' => 'ООО "BAXT TRANSPORT"', 'tin' => '308413072'];
        $other = ['id' => 'C-2', 'name' => 'BAXT TRANSPORT SERVIS', 'tin' => '300000001'];

        $found = ScrapeZanjeerOperators::fromOrders([
            $order(1, 100, $baxt, null, 'ALI'), $order(2, 100, $baxt, null, 'ALI'), // bitta asosiy buyurtma
            $order(3, 101, null, $baxt, 'VALI'), $order(4, 102, $baxt, null, 'VALI'),  // mijoz sifatida ham sanaladi
            $order(5, 103, $other, null, 'ALI'), $order(6, 104, $other, null, 'ALI'), // boshqa kompaniya — hisobga olinmaydi
        ], 'OOO "BAXT TRANSPORT"');

        $this->assertSame(['operator' => 'VALI', 'tin' => '308413072'], $found);
        $this->assertSame(['operator' => '-', 'tin' => null], ScrapeZanjeerOperators::fromOrders([], 'X'));
        $this->assertSame('-', ScrapeZanjeerOperators::fromOrders([$order(1, 1, $baxt, null, 'EGS OPERATION')], 'BAXT TRANSPORT')['operator']);
    }

    public function test_zanjeer_operators_writes_manager_and_inn_and_falls_back_to_contragent(): void
    {
        Http::fake([
            self::API . '/orders*' => fn (Request $r) => Http::response(self::page($r['search'] === 'BAXT TRANSPORT'
                ? [['id' => 1, 'parent_order_id' => 100, 'carrier' => ['name' => 'BAXT TRANSPORT', 'tin' => '308413072'], 'customer' => null, 'operation' => ['name' => 'VALIYEV VALI']]]
                : [])),
            self::API . '/contragents*' => fn (Request $r) => Http::response(self::page($r['search'] === 'YANGI'
                ? [['company_name' => 'YANGI', 'tin' => '311111111', 'employee' => ['name' => 'KARIMOV ANVAR']]]
                : [])),
        ]);

        $relative = 'tmp/egs-test-' . uniqid() . '.xlsx';
        $input = storage_path("app/{$relative}");
        @mkdir(dirname($input), 0775, true);
        $book = new Spreadsheet();
        $book->getActiveSheet()->fromArray([['A', 'B', 'C', 'INN', 'Firma nomi'], ['', '', '', '', 'OOO "BAXT TRANSPORT"'], ['', '', '', '', 'OOO "BAXT TRANSPORT"'],
            ['', '', '', '999999999', 'OOO "YANGI"'], ['', '', '', '', 'OOO "YO\'Q"']]);
        (new Xlsx($book))->save($input);

        $lastFile = storage_path('app/last_operator_file.txt');
        $previous = is_file($lastFile) ? file_get_contents($lastFile) : null;

        try {
            $this->artisan('scrape:zanjeer-operators', ['file' => $relative])
                ->expectsOutputToContain('Kompaniyalar: 3 ta · menejer topildi: 2 · INN (CRM\'dan): 2')
                ->assertSuccessful();

            $output = trim(file_get_contents($lastFile));
            $rows = IOFactory::load($output)->getActiveSheet()->toArray(null, false, false, true);
            $this->assertSame('Meneger Ismi sharifi', $rows[1]['H']);
            $this->assertSame(['VALIYEV VALI', '308413072'], [$rows[2]['H'], $rows[2]['D']]);
            $this->assertSame(['VALIYEV VALI', '308413072'], [$rows[3]['H'], $rows[3]['D']]);
            $this->assertSame(['KARIMOV ANVAR', 999999999], [$rows[4]['H'], $rows[4]['D']]); // bor INN ustidan yozilmaydi
            $this->assertSame(['-', null], [$rows[5]['H'], $rows[5]['D']]);
            // BAXT: faqat buyurtmalar; YANGI va YO'Q: buyurtma + kontragent; takror kompaniya so'ralmaydi
            Http::assertSentCount(5);
            @unlink($output);
        } finally {
            @unlink($input);
            $previous === null ? @unlink($lastFile) : file_put_contents($lastFile, $previous);
        }
    }

    public function test_fill_inn_queries_only_missing_companies_once(): void
    {
        Http::fake(['orginfo.uz/*' => Http::response(OrginfoClientTest::page([['308413072', '"BAXT TRANSPORT" mas`uliyati cheklangan jamiyati']]))]);

        $input = $this->excel([['A', 'B', 'C', 'INN', 'Firma nomi', 'F', 'Telefon'], ['', '', '', '', 'OOO "BAXT TRANSPORT"', '', 998901234567],
            ['', '', '', '', 'OOO "BAXT TRANSPORT"', '', ''], ['', '', '', '300000001', 'OOO "BOR"', '', '']]);
        $output = "{$this->dir}/final.xlsx";

        $this->artisan('excel:fill-inn', ['file' => $input, 'output' => $output])
            ->expectsOutputToContain("INN'siz kompaniyalar: 1 ta (2 qator)")
            ->expectsOutputToContain('INN topildi: 1')
            ->assertSuccessful();

        Http::assertSentCount(1);
        $rows = IOFactory::load($output)->getActiveSheet()->toArray(null, false, false, true);
        $this->assertSame(['308413072', '308413072', 300000001], [$rows[2]['D'], $rows[3]['D'], $rows[4]['D']]);
        $this->assertSame('998901234567', $rows[2]['G']);
        $this->assertSame('Chegara nomi', $rows[1]['A']);
    }
}
