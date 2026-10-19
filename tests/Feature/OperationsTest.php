<?php

namespace Tests\Feature;

use App\Jobs\RunOperationJob;
use App\Models\JobRun;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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

        $this->postJson('/operations/pipeline/run')->assertStatus(202);

        $run = JobRun::sole();
        $this->assertSame('pipeline', $run->operation);
        $this->assertSame(JobRun::QUEUED, $run->status);
        Queue::assertPushedOn('operations', RunOperationJob::class, fn ($job) => $job->run->is($run));

        $this->postJson('/operations/pipeline/run')->assertStatus(409);
        $this->assertSame(1, JobRun::count());

        // boshqa amal bunga bog'liq emas
        $this->postJson('/operations/telegram/run')->assertStatus(202);
    }

    public function test_stale_run_does_not_block(): void
    {
        Queue::fake();
        $this->actingAs($this->superAdmin());

        $stale = JobRun::create(['operation' => 'pipeline', 'status' => JobRun::RUNNING]);
        JobRun::whereKey($stale->id)->update(['updated_at' => now()->subHours(3)]);

        $this->postJson('/operations/pipeline/run')->assertStatus(202);
        $this->getJson("/operations/runs/{$stale->id}")->assertJson(['status' => 'stale', 'active' => false]);
    }

    public function test_each_operation_needs_its_own_permission(): void
    {
        Queue::fake();
        $this->actingAs($this->userWith(['operation.telegram']));

        $this->get('/operations')->assertOk()->assertSee('Telegram')->assertDontSee('data-operation="pipeline"', false);
        $this->postJson('/operations/pipeline/run')->assertForbidden();
        $this->postJson('/operations/telegram/run')->assertStatus(202);

        $pipelineRun = JobRun::create(['operation' => 'pipeline', 'status' => JobRun::SUCCESS]);
        $this->getJson("/operations/runs/{$pipelineRun->id}")->assertForbidden();

        $runs = $this->getJson('/operations/runs')->assertOk()->json('runs');
        $this->assertSame(['telegram'], array_column($runs, 'operation'));
    }

    public function test_operations_page_requires_an_operation_permission(): void
    {
        $this->actingAs($this->userWith(['page.zitic']));

        $this->get('/operations')->assertForbidden();
        $this->postJson('/operations/pipeline/run')->assertForbidden();
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
}
