<?php

namespace Tests\Feature;

use App\Jobs\RunOperationJob;
use App\Models\JobRun;
use App\Models\SpotEvent;
use App\Models\SpotEventKeyword;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SpotEventsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        Carbon::setTestNow('2026-11-12 10:00:00');
    }

    private function userWith(array $permissions): User
    {
        $role = Role::create(['name' => 'r' . uniqid(), 'guard_name' => 'web']);
        $role->syncPermissions($permissions);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function event(string $slug, array $attributes = []): SpotEvent
    {
        return SpotEvent::create($attributes + [
            'url' => "https://www.spot.uz/ru/2026/11/01/{$slug}/",
            'title' => $slug,
            'date_text' => '14 ноября (суббота)',
            'starts_on' => '2026-11-14',
            'ends_on' => '2026-11-14',
        ]);
    }

    public function test_page_lists_events_upcoming_first_and_filters(): void
    {
        $this->event('Старый форум', ['starts_on' => '2026-11-01', 'ends_on' => '2026-11-02', 'matched_keyword' => 'форум']);
        $this->event('Бизнес-завтрак', ['starts_on' => '2026-11-20', 'ends_on' => '2026-11-20', 'matched_keyword' => 'бизнес-завтрак', 'notified_at' => now()]);
        $this->event('B2B-встреча', ['starts_on' => '2026-11-13', 'ends_on' => '2026-11-13', 'matched_keyword' => 'B2B']);
        $this->event('PizzaPitch');

        $this->actingAs($this->userWith(['page.spot-events']))
            ->get(route('spot-events.index'))
            ->assertOk()
            ->assertSee('Qidirish')
            ->assertSeeInOrder(['B2B-встреча', 'PizzaPitch', 'Бизнес-завтрак', 'Старый форум'])
            ->assertSee('Yuborilgan')
            ->assertSee('Navbatda')
            ->assertSee("O'tib ketgan", false);

        $this->get(route('spot-events.index', ['filter' => 'sent']))
            ->assertSee('Бизнес-завтрак')->assertDontSee('B2B-встреча');

        $this->get(route('spot-events.index', ['q' => 'pizza']))
            ->assertSee('PizzaPitch')->assertDontSee('Бизнес-завтрак');
    }

    public function test_keywords_are_seeded_and_managed_from_the_page(): void
    {
        $this->assertSame(['B2B', 'форум', 'бизнес-завтрак'], SpotEventKeyword::list());

        $this->event('Международная конференция Outsourcing', ['starts_on' => '2026-11-25', 'ends_on' => '2026-11-25']);
        $this->event('Конференция прошлого года', ['starts_on' => '2026-11-01', 'ends_on' => '2026-11-01']);
        $this->actingAs($this->userWith(['page.spot-events']));

        $this->post(route('spot-events.keywords.store'), ['keyword' => '  конференция '])
            ->assertRedirect(route('spot-events.index'))
            ->assertSessionHas('status', fn ($status) => str_contains($status, 'Unga mos 1 ta yangi tadbir bor'));
        $this->assertContains('конференция', SpotEventKeyword::list());
        $this->assertSame('конференция', SpotEvent::where('title', 'like', 'Международная%')->value('matched_keyword'));

        // dublikat (katta harf, defis/bo'shliq farqi bilan ham)
        $this->post(route('spot-events.keywords.store'), ['keyword' => 'Бизнес завтрак'])
            ->assertSessionHasErrorsIn('keyword', 'keyword');
        $this->post(route('spot-events.keywords.store'), ['keyword' => ''])
            ->assertSessionHasErrorsIn('keyword', 'keyword');

        $keyword = SpotEventKeyword::where('keyword', 'конференция')->sole();
        $this->delete(route('spot-events.keywords.destroy', $keyword))->assertRedirect(route('spot-events.index'));
        $this->assertNotContains('конференция', SpotEventKeyword::list());
        $this->assertNull(SpotEvent::where('title', 'like', 'Международная%')->value('matched_keyword'));
    }

    public function test_page_needs_its_permission(): void
    {
        $this->actingAs($this->userWith(['page.zitic']))->get(route('spot-events.index'))->assertForbidden();
        $this->post(route('spot-events.run'))->assertForbidden();
        $this->post(route('spot-events.keywords.store'), ['keyword' => 'x'])->assertForbidden();
    }

    public function test_search_button_queues_spot_events_once(): void
    {
        Queue::fake();
        $this->actingAs($this->userWith(['page.spot-events']));

        $this->postJson(route('spot-events.run'))->assertStatus(202)->assertJsonPath('run.status', 'queued');
        $this->postJson(route('spot-events.run'))->assertStatus(409);

        Queue::assertPushed(RunOperationJob::class, 1);
        $run = JobRun::sole();
        $this->assertSame('spot-events', $run->operation);
        $this->assertSame([['spot:events', []]], RunOperationJob::commandsFor($run));

        $this->getJson(route('spot-events.status'))->assertJsonPath('run.active', true);
    }

    public function test_status_shows_command_summary(): void
    {
        $user = $this->userWith(['page.spot-events']);
        $run = JobRun::create(['operation' => 'spot-events', 'status' => JobRun::SUCCESS, 'user_id' => $user->id, 'finished_at' => now()]);
        $run->appendOutput("\n▶ php artisan spot:events  [10:00:00]\n20 ta tadbir topildi.\n[e'lon] B2B-встреча (B2B)\n1 ta yangi e'lon.\n0 ta eslatma.\n✔ tugadi (kod 0)  [10:00:05]\n");
        $run->save();

        $this->actingAs($user)->getJson(route('spot-events.status'))
            ->assertJsonPath('run.active', false)
            ->assertJsonPath('run.summary', "20 ta tadbir topildi. 1 ta yangi e'lon. 0 ta eslatma.");
    }
}
