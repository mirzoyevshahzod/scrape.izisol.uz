<?php

namespace Tests\Feature;

use App\Jobs\RunOperationJob;
use App\Models\JobRun;
use App\Models\MintransNotFound;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MintransTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        Sleep::fake();

        $this->dir = sys_get_temp_dir() . '/mintrans-test-' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("{$this->dir}/*"));
        rmdir($this->dir);

        parent::tearDown();
    }

    private function inputFile(array $values): string
    {
        $book = new Spreadsheet();
        $book->getActiveSheet()->fromArray(array_map(fn ($v) => [$v], $values));
        $path = "{$this->dir}/input-" . uniqid() . '.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }

    private function rows(string $path): array
    {
        return IOFactory::load($path)->getActiveSheet()->toArray(null, false, false, true);
    }

    /** 10S208NB topiladi, 01Z001ZZ topilmaydi, 75W606KA'da server xatosi */
    private function fakeApi(): void
    {
        Http::fake(function ($request) {
            return match ($request['autoN']) {
                '10S208NB' => Http::response(['data' => MintransClientTest::html("Rusumi: MAN TGX Yuk ko'tarish qobiliyati: 0.00", [
                    ['license' => 'AT 10437261', 'plate' => '10S208NB', 'company' => 'МЧЖ "BAXT TRANSPORT" MAS\'ULIYATI CHEKLANGAN JAMIYAT'],
                    ['license' => 'AT 10437262', 'plate' => '10S208NB', 'company' => "O'O'B X******* M******"],
                ])]),
                '75W606KA' => Http::response('down', 500),
                default => Http::response(['data' => null, 'pdfUrl' => null]),
            };
        });
    }

    private function user(array $permissions): User
    {
        $role = Role::create(['name' => 'r' . uniqid(), 'guard_name' => 'web']);
        $role->syncPermissions($permissions);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    // --- 1-bosqich ---

    public function test_scrape_writes_found_rows_and_records_missing_plates(): void
    {
        $this->fakeApi();
        $input = $this->inputFile(['Avtoraqamlar', '10 S 208 NB', 'Ali Valiyev', '506695BA', '01Z001ZZ', '75W606KA', '10S208NB']);
        $output = "{$this->dir}/result.xlsx";

        $this->artisan('mintrans:scrape', ['file' => $input, '--output' => $output])
            ->expectsOutputToContain("✅ [1/3] 10S208NB — ma'lumot chiqdi (2 ta litsenziya)")
            ->expectsOutputToContain("❌ [2/3] 01Z001ZZ — ma'lumot chiqmadi")
            ->expectsOutputToContain('⚠️ [3/3] 75W606KA — xatolik')
            ->expectsOutputToContain('Jami: 3 · Topildi: 1 · Topilmadi: 1 · Xatolik: 1')
            ->assertSuccessful();

        $rows = $this->rows($output);
        $this->assertSame(['Rusumi', "Yuk ko'tarish qobiliyati", 'Litsenziya varaqasi', 'Davlat raqami', 'Korxona nomi', 'Telefon raqami',
            'Faoliyat turi', 'Transport turi', 'Yuk turi', 'Berilgan sana', 'Amal qilish muddati', 'Holati', 'Hududiy boshqarma'], array_values($rows[1]));
        $this->assertCount(3, $rows); // sarlavha + 10S208NB'ning 2 ta litsenziyasi (topilmaganlar Excel'ga yozilmaydi)
        $this->assertSame('10S208NB', $rows[2]['D']);
        $this->assertSame('AT 10437262', $rows[3]['C']);
        $this->assertSame('MAN TGX', $rows[2]['A']);

        $this->assertSame(MintransNotFound::NOT_FOUND, MintransNotFound::where('plate', '01Z001ZZ')->value('status'));
        $this->assertSame(MintransNotFound::ERROR, MintransNotFound::where('plate', '75W606KA')->value('status'));
        $this->assertDatabaseMissing('mintrans_not_found', ['plate' => '10S208NB']);
    }

    public function test_skip_known_missing_and_found_plate_leaves_the_list(): void
    {
        $this->fakeApi();
        MintransNotFound::record('01Z001ZZ', MintransNotFound::NOT_FOUND);
        MintransNotFound::record('10S208NB', MintransNotFound::ERROR, 'timeout'); // xatolilar o'tkazib yuborilmaydi
        $input = $this->inputFile(['01Z001ZZ', '10S208NB']);

        $this->artisan('mintrans:scrape', ['file' => $input, '--output' => "{$this->dir}/r.xlsx", '--skip-known-missing' => true])
            ->expectsOutputToContain("avval topilmagani uchun o'tkazib yuborildi: 1 ta")
            ->expectsOutputToContain("✅ [1/1] 10S208NB — ma'lumot chiqdi")
            ->assertSuccessful();

        Http::assertNotSent(fn ($request) => $request['autoN'] === '01Z001ZZ');
        $this->assertDatabaseMissing('mintrans_not_found', ['plate' => '10S208NB']); // topildi — ro'yxatdan chiqdi
        $this->assertSame(1, MintransNotFound::where('plate', '01Z001ZZ')->value('checks'));

        // tanlov o'chiq bo'lsa — qayta so'raladi va hisoblagich oshadi
        $this->artisan('mintrans:scrape', ['file' => $this->inputFile(['01Z001ZZ']), '--output' => "{$this->dir}/r2.xlsx"])->assertSuccessful();
        $this->assertSame(2, MintransNotFound::where('plate', '01Z001ZZ')->value('checks'));
    }

    public function test_scrape_fails_when_every_request_errors(): void
    {
        Http::fake(['info.mintrans.uz/*' => Http::response('down', 500)]);

        $this->artisan('mintrans:scrape', ['file' => $this->inputFile(['01A222MC']), '--output' => "{$this->dir}/r.xlsx"])->assertFailed();
    }

    public function test_scrape_updates_job_run_progress(): void
    {
        $this->fakeApi();
        $run = JobRun::create(['operation' => 'mintrans', 'status' => JobRun::RUNNING, 'params' => ['original_name' => 'a.xlsx']]);

        $this->artisan('mintrans:scrape', ['file' => $this->inputFile(['10S208NB', '01Z001ZZ']), '--output' => "{$this->dir}/r.xlsx", '--run' => $run->id])
            ->assertSuccessful();

        $params = $run->fresh()->params;
        $this->assertSame('a.xlsx', $params['original_name']);
        $this->assertSame(['total' => 2, 'done' => 2, 'found' => 1, 'not_found' => 1, 'errors' => 0, 'dropped' => 0, 'skipped' => 0], $params['progress']);
    }

    // --- 2-bosqich ---

    public function test_clean_names_formats_company_column(): void
    {
        $this->fakeApi();
        $output = "{$this->dir}/result.xlsx";
        $this->artisan('mintrans:scrape', ['file' => $this->inputFile(['10S208NB']), '--output' => $output])->assertSuccessful();

        $this->artisan('mintrans:clean-names', ['file' => $output])
            ->expectsOutputToContain('Korxona nomlari: 2 ta, shundan formatlandi: 2 ta')
            ->assertSuccessful();

        $rows = $this->rows($output);
        $this->assertSame('OOO "BAXT TRANSPORT"', $rows[2]['E']);
        $this->assertSame('OOO "X******* M******"', $rows[3]['E']);
    }

    // --- job parametrlari ---

    public function test_job_builds_both_stage_commands(): void
    {
        $run = JobRun::create(['operation' => 'mintrans', 'status' => JobRun::QUEUED, 'params' => [
            'file' => '/in.xlsx', 'result' => '/out.xlsx', 'skip_missing' => true,
        ]]);

        $this->assertSame([
            ['mintrans:scrape', ['file' => '/in.xlsx', '--output' => '/out.xlsx', '--run' => $run->id, '--skip-known-missing' => true]],
            ['mintrans:clean-names', ['file' => '/out.xlsx']],
            ['mintrans:inn', ['file' => '/out.xlsx', '--run' => $run->id]],
        ], RunOperationJob::commandsFor($run));

        $run->update(['params' => ['file' => '/in.xlsx', 'result' => '/out.xlsx', 'skip_missing' => false]]);
        $this->assertArrayNotHasKey('--skip-known-missing', RunOperationJob::commandsFor($run->fresh())[0][1]);
    }

    // --- sahifa ---

    public function test_upload_queues_a_run(): void
    {
        Queue::fake();
        $this->actingAs($this->user(['page.mintrans-auto']));
        $file = new UploadedFile($this->inputFile(['10S208NB']), 'raqamlar.xlsx', null, null, true);

        $this->post('/mintrans-auto/start', ['file' => $file, 'skip_missing' => '1'], ['Accept' => 'application/json'])
            ->assertStatus(202)
            ->assertJsonPath('run.file', 'raqamlar.xlsx');

        $run = JobRun::sole();
        $this->assertSame('mintrans', $run->operation);
        $this->assertTrue($run->params['skip_missing']);
        $this->assertFileExists($run->params['file']);
        $this->assertStringNotContainsString('raqamlar', $run->params['file']); // diskdagi nom foydalanuvchidan olinmaydi
        Queue::assertPushedOn('operations', RunOperationJob::class);

        // ikkinchisi birinchisi tugamaguncha qabul qilinmaydi
        $again = new UploadedFile($this->inputFile(['10S208NB']), 'b.xlsx', null, null, true);
        $this->post('/mintrans-auto/start', ['file' => $again], ['Accept' => 'application/json'])->assertStatus(409);

        unlink($run->params['file']);
    }

    public function test_upload_validation(): void
    {
        $this->actingAs($this->user(['page.mintrans-auto']));
        $txt = UploadedFile::fake()->createWithContent('a.txt', 'salom');

        $this->postJson('/mintrans-auto/start', ['file' => $txt])->assertStatus(422)->assertJsonValidationErrors('file');
        $this->postJson('/mintrans-auto/start', [])->assertStatus(422);
    }

    public function test_page_requires_permission_and_old_routes_are_gone(): void
    {
        $this->actingAs($this->user(['page.zitic']));
        $this->get('/mintrans-auto')->assertForbidden();
        $this->get('/mintrans-auto/ping')->assertForbidden();

        $this->actingAs($this->user(['page.mintrans-auto']));
        $this->get('/mintrans-auto')->assertOk()->assertSee('API\'ni tekshirish', false);

        foreach (['/mintrans-litsenziya', '/api/mintrans/all-files', '/api/download/abc'] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->postJson('/api/upload')->assertNotFound();
        $this->postJson('/api/mintrans/submit')->assertNotFound();
        // GET /api/scrape/all-files endi faqat Belarus'ning POST /api/scrape/{zone} route'iga tushadi (405)
        $this->getJson('/api/scrape/all-files')->assertStatus(405);
    }

    public function test_mintrans_is_hidden_from_boshqaruv(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $this->get('/operations')->assertOk()->assertDontSee('data-operation="mintrans"', false);
        $this->postJson('/operations/mintrans/run')->assertNotFound();
    }

    public function test_run_details_download_and_missing_list(): void
    {
        $this->actingAs($this->user(['page.mintrans-auto']));
        $result = "{$this->dir}/mintrans_result_x.xlsx";
        copy($this->inputFile(['x']), $result);
        $run = JobRun::create(['operation' => 'mintrans', 'status' => JobRun::SUCCESS, 'output' => "✅ [1/1] 10S208NB — ma'lumot chiqdi\n",
            'params' => ['original_name' => 'a.xlsx', 'result' => $result, 'progress' => ['total' => 1, 'done' => 1, 'found' => 1, 'not_found' => 0, 'errors' => 0, 'dropped' => 0, 'skipped' => 0]]]);
        $other = JobRun::create(['operation' => 'pipeline', 'status' => JobRun::SUCCESS]);

        $this->getJson("/mintrans-auto/runs/{$run->id}")->assertOk()->assertJsonPath('output', "✅ [1/1] 10S208NB — ma'lumot chiqdi\n");
        $this->getJson('/mintrans-auto/runs')->assertOk()->assertJsonPath('runs.0.downloadable', true);
        $this->get("/mintrans-auto/runs/{$run->id}/download")->assertOk()->assertDownload('mintrans_result_x.xlsx');
        $this->getJson("/mintrans-auto/runs/{$other->id}")->assertNotFound(); // boshqa amalning ishi ko'rinmaydi

        MintransNotFound::record('01Z001ZZ', MintransNotFound::NOT_FOUND);
        MintransNotFound::record('75W606KA', MintransNotFound::ERROR, 'timeout');
        $this->getJson('/mintrans-auto/missing?q=01z')->assertOk()->assertJsonPath('total', 1)->assertJsonCount(1, 'items');
        $this->get('/mintrans-auto/missing/export')->assertOk()->assertDownload();
    }

    public function test_ping_endpoint(): void
    {
        Http::fake(['info.mintrans.uz/*' => Http::response('down', 502)]);
        $this->actingAs($this->user(['page.mintrans-auto']));

        $this->getJson('/mintrans-auto/ping')->assertOk()->assertJson(['ok' => false]);
    }

    // --- faqat 2-bosqich ---

    private function companyFile(): string
    {
        $book = new Spreadsheet();
        $book->getActiveSheet()->fromArray([
            ['Rusumi', 'Davlat raqami', 'Korxona nomi'],
            ['MAN', '10S208NB', 'МЧЖ "BAXT TRANSPORT" MAS\'ULIYATI CHEKLANGAN JAMIYAT'],
            ['LACETTI', '01Z001AA', "O'O'B T******* A******"],
        ]);
        $path = "{$this->dir}/names-" . uniqid() . '.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }

    public function test_names_only_upload_runs_just_stage_two(): void
    {
        Queue::fake();
        $this->actingAs($this->user(['page.mintrans-auto']));
        $file = new UploadedFile($this->companyFile(), 'mintrans_result 28.10.xlsx', null, null, true);

        $this->post('/mintrans-auto/start', ['file' => $file, 'stage' => 'names'], ['Accept' => 'application/json'])
            ->assertStatus(202)
            ->assertJsonPath('run.stage', 'names');

        $run = JobRun::sole();
        $this->assertSame('mintrans-names', $run->operation);
        $this->assertSame('mintrans_result_28_10_formatlangan.xlsx', $run->params['download_name']);
        $this->assertSame([
            ['mintrans:clean-names', ['file' => $run->params['file'], '--output' => $run->params['result']]],
            ['mintrans:inn', ['file' => $run->params['result'], '--run' => $run->id]],
        ], RunOperationJob::commandsFor($run));

        unlink($run->params['file']);
    }

    public function test_names_only_requires_company_column(): void
    {
        Queue::fake();
        $this->actingAs($this->user(['page.mintrans-auto']));
        $file = new UploadedFile($this->inputFile(['10S208NB']), 'raqamlar.xlsx', null, null, true);

        $this->post('/mintrans-auto/start', ['file' => $file, 'stage' => 'names'], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Faylda "Korxona nomi" ustuni topilmadi.');

        $this->assertSame(0, JobRun::count());
        Queue::assertNothingPushed();
    }

    public function test_names_only_can_run_while_a_full_run_is_active(): void
    {
        Queue::fake();
        $this->actingAs($this->user(['page.mintrans-auto']));
        JobRun::create(['operation' => 'mintrans', 'status' => JobRun::RUNNING, 'params' => ['original_name' => 'a.xlsx']]);

        $file = new UploadedFile($this->companyFile(), 'n.xlsx', null, null, true);
        $this->post('/mintrans-auto/start', ['file' => $file, 'stage' => 'names'], ['Accept' => 'application/json'])->assertStatus(202);

        $stages = collect($this->getJson('/mintrans-auto/runs')->json('runs'))->pluck('stage')->all();
        $this->assertSame(['names', 'full'], $stages);

        unlink(JobRun::where('operation', 'mintrans-names')->sole()->params['file']);
    }

    public function test_names_only_job_formats_and_downloads_with_new_name(): void
    {
        Http::fake(['orginfo.uz/*' => Http::response(OrginfoClientTest::page([['308413072', '"BAXT TRANSPORT" mas`uliyati cheklangan jamiyati']]))]);
        $this->actingAs($this->user(['page.mintrans-auto']));
        $input = $this->companyFile();
        $run = JobRun::create(['operation' => 'mintrans-names', 'status' => JobRun::QUEUED, 'params' => [
            'file' => $input, 'original_name' => 'x.xlsx', 'result' => "{$this->dir}/out/natija.xlsx", 'download_name' => 'x_formatlangan.xlsx',
        ]]);

        (new RunOperationJob($run))->handle();

        $this->assertSame(JobRun::SUCCESS, $run->fresh()->status);
        $rows = $this->rows("{$this->dir}/out/natija.xlsx");
        $this->assertSame('OOO "BAXT TRANSPORT"', $rows[2]['C']);
        $this->assertSame('OOO "T******* A******"', $rows[3]['C']);
        // 3-bosqich ham ishladi: auto_import_template ustunlari
        $this->assertSame(['инн', 'Yangi telefon raqami'], [$rows[1]['N'], $rows[1]['O']]);
        $this->assertSame('308413072', $rows[2]['N']);
        $this->assertNull($rows[3]['N']);
        $this->assertStringContainsString('МЧЖ', $this->rows($input)[2]['C']); // yuklangan asl fayl o'zgarmaydi

        $this->get("/mintrans-auto/runs/{$run->id}/download")->assertOk()->assertDownload('x_formatlangan.xlsx');

        unlink("{$this->dir}/out/natija.xlsx");
        rmdir("{$this->dir}/out");
    }

    // --- faqat 3-bosqich ---

    public function test_inn_only_upload(): void
    {
        Queue::fake();
        $this->actingAs($this->user(['page.mintrans-auto']));
        $file = new UploadedFile($this->companyFile(), 'tayyor.xlsx', null, null, true);

        $this->post('/mintrans-auto/start', ['file' => $file, 'stage' => 'inn'], ['Accept' => 'application/json'])
            ->assertStatus(202)
            ->assertJsonPath('run.stage', 'inn');

        $run = JobRun::sole();
        $this->assertSame('mintrans-inn', $run->operation);
        $this->assertSame('tayyor_inn.xlsx', $run->params['download_name']);
        $this->assertSame([['mintrans:inn', ['file' => $run->params['file'], '--output' => $run->params['result'], '--run' => $run->id]]], RunOperationJob::commandsFor($run));

        // "Korxona nomi" ustuni bo'lmasa — rad etiladi
        $bad = new UploadedFile($this->inputFile(['10S208NB']), 'raqam.xlsx', null, null, true);
        $this->post('/mintrans-auto/start', ['file' => $bad, 'stage' => 'inn'], ['Accept' => 'application/json'])->assertStatus(422);

        unlink($run->params['file']);
    }
}
