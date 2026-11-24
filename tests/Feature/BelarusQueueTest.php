<?php

namespace Tests\Feature;

use App\Http\Controllers\API\BelarusQueueController;
use App\Models\Auto;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class BelarusQueueTest extends TestCase
{
    use RefreshDatabase;

    private const BENYAKONI = '53d94097-2b34-11ec-8467-ac1f6bf889c0';

    private const KAMENNII_LOG = 'b60677d4-8a00-4f93-a781-e129e1692a03';

    private const CRM = 'https://crm.test/api/v1/integration';

    private string $dir;

    /** @var list<array> Telegram'ga ketgan so'rovlar */
    private array $telegram = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . '/belarus-test-' . uniqid();
        mkdir($this->dir);
        app()->useStoragePath($this->dir);

        // autos — eski (migratsiyasiz) jadval, prod'da bor
        Schema::create('autos', function (Blueprint $table) {
            $table->id();
            $table->string('state_number');
            $table->string('company_name')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });
        Auto::create(['state_number' => '01A123BC', 'company_name' => 'OOO TEST', 'phone' => '998901234567']);

        config([
            'belarus_queue.ingest_token' => 'secret',
            'belarus_queue.zones' => ['benyakoni', 'kamennii-log'],
            'belarus_queue.vnc_url' => 'https://scrape.test/belarus-vnc/vnc.html',
            'belarus_queue.remind_hours' => '0-24',
            'services.telegram.daily_files_bot_token' => 'bot',
            'services.telegram.report_chat_id' => '555',
            'crm.egs.base_url' => self::CRM,
            'crm.egs.api_key' => 'key',
        ]);

        Sleep::fake();
        Http::preventStrayRequests();
        Http::fake([
            'api.telegram.org/*' => function (Request $r) {
                $this->telegram[] = $r->data();

                return Http::response(['ok' => true]);
            },
            self::CRM . '/borders*' => Http::response(['success' => true, 'data' => ['current_page' => 1, 'last_page' => 1, 'data' => [
                ['id' => 7, 'name' => 'Бенякони - Шальчининкай'],
                ['id' => 8, 'name' => 'Каменный Лог - Мядининкай'],
            ]]]),
            self::CRM . '/custom-operations/import' => Http::response(['success' => true, 'data' => ['rows_count' => 1]]),
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    private static function response(string $checkpoint, array $trucks = [['regnum' => '01A123BC'], ['regnum' => 'LZD200']]): array
    {
        return [
            'info' => ['id' => $checkpoint, 'name' => 'X'],
            'truckLiveQueue' => array_map(fn ($t) => $t + ['status' => 2, 'order_id' => 1, 'type_queue' => 3,
                'registration_date' => '12:00:10 23.11.2026', 'changed_date' => '12:00:10 23.11.2026'], $trucks),
            'truckPriority' => [],
            'carLiveQueue' => [],
        ];
    }

    private function ingest(array $payload, string $token = 'secret')
    {
        return $this->postJson('/api/belarus-queue', $payload, ['X-Ingest-Token' => $token]);
    }

    private function files(): array
    {
        return array_map('basename', glob("{$this->dir}/app/declarant/*.xlsx") ?: []);
    }

    private function messages(): array
    {
        return array_values(array_filter($this->telegram, fn ($m) => isset($m['text'])));
    }

    // --- qabul qilish ---

    public function test_ingest_checks_token_checkpoint_and_empty_queue(): void
    {
        $this->ingest(self::response(self::BENYAKONI), 'wrong')->assertForbidden();

        config(['belarus_queue.ingest_token' => null]);
        $this->ingest(self::response(self::BENYAKONI), '')->assertForbidden();
        config(['belarus_queue.ingest_token' => 'secret']);

        $this->ingest(self::response('unknown'))->assertUnprocessable()->assertJson(['error' => 'unknown checkpoint']);

        // captcha'siz sahifa oladigan javob: info bor, ro'yxatlar bo'sh
        $this->ingest(self::response(self::BENYAKONI, []))->assertUnprocessable()->assertJson(['zone' => 'benyakoni', 'error' => 'empty queue']);
        $this->assertNull(Cache::get(BelarusQueueController::PENDING_KEY . 'benyakoni'));

        $this->ingest(self::response(self::BENYAKONI))->assertOk()->assertJson(['ok' => true, 'zone' => 'benyakoni', 'trucks' => 2]);
        $this->assertNotNull(Cache::get(BelarusQueueController::PENDING_KEY . 'benyakoni'));
    }

    // --- Excel + CRM ---

    public function test_sync_waits_for_all_zones_then_exports_and_imports_once(): void
    {
        $this->ingest(self::response(self::BENYAKONI))->assertOk();

        // Каменный Лог hali kelmagan — kutadi
        $this->artisan('belarus:queue-sync')->expectsOutputToContain('Kutilmoqda: kamennii-log')->assertSuccessful();
        $this->assertSame([], $this->files());

        $this->ingest(self::response(self::KAMENNII_LOG, [['regnum' => '01A123BC'], ['regnum' => '10777ABC'], ['regnum' => 'PL1234']]))->assertOk();
        $this->artisan('belarus:queue-sync')->assertSuccessful();

        $files = $this->files();
        $this->assertCount(2, $files);

        $benyakoni = glob("{$this->dir}/app/declarant/benyakoni-*.xlsx")[0];
        $rows = IOFactory::load($benyakoni)->getActiveSheet()->toArray();
        $this->assertSame('Рег.номер', $rows[0][2]);
        $this->assertCount(2, $rows); // sarlavha + bitta o'zbek raqami (LZD200 — o'zbek emas)
        $this->assertSame(['01A123BC', 'Живая очередь', 'Прибыл в ЗО', 'OOO TEST', '998901234567', 'Бенякони'],
            [$rows[1][2], $rows[1][1], $rows[1][5], $rows[1][6], $rows[1][7], $rows[1][9]]);

        // ikkala zona bitta import bilan CRM'ga ketdi, hisobot Telegram'ga
        Http::assertSentCount(count($this->telegram) + 3); // borders + 2 ta import
        $report = collect($this->messages())->first(fn ($m) => str_contains($m['text'], 'CRM import'));
        $this->assertStringContainsString('Declarant', $report['text']);
        $this->assertStringContainsString('Бенякони - Шальчининкай', $report['text']);

        // kutilayotganlar tozalandi — qayta ishga tushganda yana import qilinmaydi
        $this->artisan('belarus:queue-sync')->assertSuccessful();
        $this->assertCount(2, $this->files());
    }

    public function test_sync_does_not_wait_forever_for_missing_zone(): void
    {
        $this->ingest(self::response(self::BENYAKONI))->assertOk();
        Carbon::setTestNow(now()->addSeconds(91));

        $this->artisan('belarus:queue-sync')->assertSuccessful();

        $this->assertCount(1, $this->files());
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'custom-operations/import'));
    }

    // --- eslatma ---

    public function test_reminds_with_vnc_button_when_data_is_stale_once_per_period(): void
    {
        $this->ingest(self::response(self::BENYAKONI))->assertOk();
        $this->ingest(self::response(self::KAMENNII_LOG))->assertOk();
        $this->artisan('belarus:queue-sync')->assertSuccessful();
        $this->telegram = [];

        // yangi ma'lumot — eslatma yo'q
        $this->artisan('belarus:queue-sync')->assertSuccessful();
        $this->assertSame([], $this->messages());

        Carbon::setTestNow(now()->addMinutes(61));
        $this->artisan('belarus:queue-sync')->expectsOutputToContain('Captcha eslatmasi yuborildi.')->assertSuccessful();

        $message = $this->messages()[0];
        $this->assertSame('555', $message['chat_id']);
        $this->assertStringContainsString('Belarus navbati yangilanmayapti', $message['text']);
        $this->assertStringContainsString('Бенякони: oxirgi ma\'lumot', $message['text']);
        $this->assertSame('https://scrape.test/belarus-vnc/vnc.html', $message['reply_markup']['inline_keyboard'][0][0]['url']);

        // bir soat ichida qayta yuborilmaydi
        Carbon::setTestNow(now()->addMinutes(30));
        $this->artisan('belarus:queue-sync')->assertSuccessful();
        $this->assertCount(1, $this->messages());

        Carbon::setTestNow(now()->addMinutes(31));
        $this->artisan('belarus:queue-sync')->assertSuccessful();
        $this->assertCount(2, $this->messages());
    }

    public function test_no_reminder_outside_remind_hours(): void
    {
        config(['belarus_queue.remind_hours' => '8-22']);
        Carbon::setTestNow(Carbon::today()->setTime(23, 30));

        $this->artisan('belarus:queue-sync')->assertSuccessful();
        $this->assertSame([], $this->messages());

        Carbon::setTestNow(Carbon::today()->setTime(9, 0));
        $this->artisan('belarus:queue-sync')->assertSuccessful();
        $this->assertStringContainsString('hali ma\'lumot kelmagan', $this->messages()[0]['text']);
    }

    // --- eski API yo'li ---

    public function test_api_scrape_writes_no_file_when_site_returns_empty_queue(): void
    {
        Http::fake(['belarusborder.by/*' => Http::response(self::response(self::BENYAKONI, []))]);

        $this->artisan('scrape:declarant-data', ['zone' => 'benyakoni'])
            ->expectsOutputToContain('sayt captcha talab qilyapti')
            ->assertFailed();

        $this->assertSame([], $this->files());
    }
}
