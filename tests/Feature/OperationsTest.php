<?php

namespace Tests\Feature;

use App\Jobs\RunOperationJob;
use App\Models\JobRun;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use App\Support\Operations\ScheduleLock;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    private function userWith(array $permissions): User
    {
        $role = Role::create(['name' => 'r' . uniqid(), 'guard_name' => 'web']);
        $role->syncPermissions($permissions);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user;
    }

    // --- HTTP ---

    public function test_run_queues_job_and_blocks_duplicate(): void
    {
        Queue::fake();
        $this->actingAs($this->superAdmin());

        $this->postJson('/operations/pipeline/run', ['date' => today()->format('Y-m-d')])->assertStatus(202);

        $run = JobRun::sole();
        $this->assertSame('pipeline', $run->operation);
        $this->assertSame(JobRun::QUEUED, $run->status);
        Queue::assertPushedOn('operations', RunOperationJob::class, fn ($job) => $job->run->is($run));

        $this->postJson('/operations/pipeline/run', ['date' => today()->format('Y-m-d')])->assertStatus(409);
        $this->assertSame(1, JobRun::count());

        // boshqa amal bunga bog'liq emas
        $this->postJson('/operations/telegram/run', ['date' => today()->format('Y-m-d')])->assertStatus(202);
    }

    public function test_stale_run_does_not_block(): void
    {
        Queue::fake();
        $this->actingAs($this->superAdmin());

        $stale = JobRun::create(['operation' => 'pipeline', 'status' => JobRun::RUNNING]);
        JobRun::whereKey($stale->id)->update(['updated_at' => now()->subHours(3)]);

        $this->postJson('/operations/pipeline/run', ['date' => today()->format('Y-m-d')])->assertStatus(202);
        $this->getJson("/operations/runs/{$stale->id}")->assertJson(['status' => 'stale', 'active' => false]);
    }

    public function test_each_operation_needs_its_own_permission(): void
    {
        Queue::fake();
        $this->actingAs($this->userWith(['operation.telegram']));

        $this->get('/operations')->assertOk()->assertSee('Telegram')->assertDontSee('data-operation="pipeline"', false);
        $this->postJson('/operations/pipeline/run', ['date' => today()->format('Y-m-d')])->assertForbidden();
        $this->postJson('/operations/telegram/run', ['date' => today()->format('Y-m-d')])->assertStatus(202);

        $pipelineRun = JobRun::create(['operation' => 'pipeline', 'status' => JobRun::SUCCESS]);
        $this->getJson("/operations/runs/{$pipelineRun->id}")->assertForbidden();

        $runs = $this->getJson('/operations/runs')->assertOk()->json('runs');
        $this->assertSame(['telegram'], array_column($runs, 'operation'));
    }

    public function test_operations_page_requires_an_operation_permission(): void
    {
        $this->actingAs($this->userWith(['page.zitic']));

        $this->get('/operations')->assertForbidden();
        $this->postJson('/operations/pipeline/run', ['date' => today()->format('Y-m-d')])->assertForbidden();
    }

    public function test_unknown_operation_is_404(): void
    {
        $this->actingAs($this->superAdmin());

        $this->postJson('/operations/nope/run')->assertNotFound();
    }

    // --- job ---

    public function test_job_runs_all_commands_and_reports_failure(): void
    {
        config()->set('operations.operations.test', [
            'label' => 'Test', 'permission' => 'operation.pipeline',
            'commands' => [['test:does-not-exist', []], ['inspire', []]],
        ]);

        $run = JobRun::create(['operation' => 'test', 'status' => JobRun::QUEUED]);
        (new RunOperationJob($run))->handle();

        $run->refresh();
        $this->assertSame(JobRun::FAILED, $run->status);
        $this->assertNotNull($run->started_at);
        $this->assertNotNull($run->finished_at);
        // birinchisi yiqilsa ham ikkinchisi bajarilgan
        $this->assertStringContainsString('▶ php artisan test:does-not-exist', $run->output);
        $this->assertStringContainsString('▶ php artisan inspire', $run->output);
        $this->assertStringContainsString('✖ tugadi (kod 1)', $run->output);
        $this->assertStringContainsString('✔ tugadi (kod 0)', $run->output);
    }

    public function test_job_success(): void
    {
        config()->set('operations.operations.test', [
            'label' => 'Test', 'permission' => 'operation.pipeline', 'commands' => [['inspire', []]],
        ]);

        $run = JobRun::create(['operation' => 'test', 'status' => JobRun::QUEUED]);
        (new RunOperationJob($run))->handle();

        $this->assertSame(JobRun::SUCCESS, $run->fresh()->status);
    }

    public function test_job_skips_command_already_running_on_schedule_and_holds_its_mutex(): void
    {
        config()->set('operations.operations.test', [
            'label' => 'Test', 'permission' => 'operation.pipeline', 'commands' => [['inspire', []]],
        ]);

        $event = app(Schedule::class)->command('inspire')->withoutOverlapping();

        // jadval bo'yicha hozir ishlayapti
        $event->mutex->create($event);
        $run = JobRun::create(['operation' => 'test', 'status' => JobRun::QUEUED]);
        (new RunOperationJob($run))->handle();

        $run->refresh();
        $this->assertSame(JobRun::FAILED, $run->status);
        $this->assertStringContainsString('Jadval bo\'yicha hozir ishlayapti', $run->output);
        $this->assertStringNotContainsString('✔ tugadi', $run->output);

        // jadval tugadi — endi job ishlaydi va oxirida mutex'ni bo'shatadi
        $event->mutex->forget($event);
        $run = JobRun::create(['operation' => 'test', 'status' => JobRun::QUEUED]);
        (new RunOperationJob($run))->handle();

        $this->assertSame(JobRun::SUCCESS, $run->fresh()->status);
        $this->assertFalse($event->mutex->exists($event));
    }

    public function test_failed_marks_run_failed(): void
    {
        $run = JobRun::create(['operation' => 'pipeline', 'status' => JobRun::RUNNING]);

        (new RunOperationJob($run))->failed(new \RuntimeException('timeout'));

        $run->refresh();
        $this->assertSame(JobRun::FAILED, $run->status);
        $this->assertStringContainsString('timeout', $run->output);
        $this->assertNotNull($run->finished_at);
    }

    public function test_scraping_operation_matches_daily_schedule(): void
    {
        $scheduled = collect(app(Schedule::class)->events())->map->command;

        foreach (config('operations.operations.scraping.commands') as [$command, $parameters]) {
            $this->assertTrue(
                $scheduled->contains(fn ($c) => str_contains($c, $command) && collect($parameters)->every(fn ($p) => str_contains($c, $p))),
                "{$command} " . json_encode($parameters) . ' jadvalda yo\'q'
            );
        }
    }

    // --- kun tanlash ---

    public function test_date_operations_require_a_valid_past_or_today_date(): void
    {
        Queue::fake();
        $this->actingAs($this->superAdmin());

        foreach (['pipeline', 'telegram', 'crm-import'] as $operation) {
            $this->postJson("/operations/{$operation}/run")->assertStatus(422)->assertJsonValidationErrors('date');
            $this->postJson("/operations/{$operation}/run", ['date' => today()->addDay()->format('Y-m-d')])->assertStatus(422);
            $this->postJson("/operations/{$operation}/run", ['date' => '20-10-2026'])->assertStatus(422);
        }

        $this->assertSame(0, JobRun::count());
        Queue::assertNothingPushed();
    }

    public function test_selected_date_is_stored_and_returned(): void
    {
        Queue::fake();
        $this->actingAs($this->superAdmin());
        $day = today()->subDays(2)->format('Y-m-d');

        $this->postJson('/operations/crm-import/run', ['date' => $day])
            ->assertStatus(202)
            ->assertJsonPath('run.date', $day);

        $run = JobRun::sole();
        $this->assertSame(['date' => $day], $run->params);
        $this->getJson("/operations/runs/{$run->id}")->assertJson(['date' => $day]);
    }

    public function test_operations_without_date_ignore_it(): void
    {
        Queue::fake();
        $this->actingAs($this->superAdmin());

        $this->postJson('/operations/crm-login/run', ['date' => 'nonsense'])->assertStatus(202);
        $this->assertNull(JobRun::sole()->params);
    }

    public function test_crm_import_has_its_own_permission(): void
    {
        Queue::fake();
        $this->actingAs($this->userWith(['operation.telegram']));

        $this->postJson('/operations/crm-import/run', ['date' => today()->format('Y-m-d')])->assertForbidden();
        $this->get('/operations')->assertOk()->assertDontSee('data-operation="crm-import"', false);
    }

    public function test_job_passes_date_option_to_every_command(): void
    {
        $run = new JobRun(['operation' => 'scraping', 'params' => ['date' => '2026-10-20']]);
        foreach (RunOperationJob::commandsFor($run) as [, $parameters]) {
            $this->assertSame('2026-10-20', $parameters['--date']);
        }

        // sanasiz ishga tushirilganda --date qo'shilmaydi, asl parametrlar saqlanadi
        $plain = RunOperationJob::commandsFor(new JobRun(['operation' => 'scraping']));
        $this->assertSame(['checkpoint' => 'Kazakhstan - Russia'], $plain[0][1]);
    }

    public function test_schedule_lock_ignores_date_option(): void
    {
        $event = app(Schedule::class)->command('inspire')->withoutOverlapping();
        $event->mutex->create($event);

        // jadvaldagi "inspire" ishlayotgan paytda "inspire --date=..." ham to'siladi
        $this->assertFalse((new ScheduleLock('inspire', ['--date' => '2026-10-20']))->acquire());

        $event->mutex->forget($event);
        $lock = new ScheduleLock('inspire', ['--date' => '2026-10-20']);
        $this->assertTrue($lock->acquire());
        $this->assertTrue($event->mutex->exists($event));
        $lock->release();
    }

    public function test_crm_import_is_on_the_daily_schedule(): void
    {
        $this->assertTrue(collect(app(Schedule::class)->events())->contains(fn ($e) => str_ends_with($e->command, 'crm:daily-import')));
    }
}
