<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->superAdmin = User::factory()->create(['email' => 'super@test.uz']);
        $this->superAdmin->assignRole('super_admin');
    }

    private function userWith(array $permissions, string $email = 'user@test.uz'): User
    {
        $role = Role::firstOrCreate(['name' => 'role-' . md5(implode(',', $permissions)), 'guard_name' => 'web']);
        $role->syncPermissions($permissions);

        $user = User::factory()->create(['email' => $email]);
        $user->assignRole($role);

        return $user;
    }

    // --- login ---

    public function test_guest_cannot_reach_pages_or_api(): void
    {
        $this->get('/zitic')->assertRedirect('/login');
        $this->get('/api/zitic/files')->assertRedirect('/login');
        $this->get('/admin/users')->assertRedirect('/login');
    }

    public function test_telegram_webhook_stays_public(): void
    {
        $this->postJson('/api/telegram/webhook', [])->assertOk();
    }

    public function test_login_with_valid_and_invalid_credentials(): void
    {
        $this->post('/login', ['email' => 'super@test.uz', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'super@test.uz', 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($this->superAdmin);
    }

    // --- permission'lar ---

    public function test_page_permission_opens_page_and_its_api_only(): void
    {
        $this->actingAs($this->userWith(['page.zitic']));

        $this->get('/zitic')->assertOk();
        $this->get('/api/zitic/files')->assertOk();

        $this->get('/zitic-converter')->assertForbidden();
        $this->get('/api/turkey/files')->assertForbidden();
        $this->get('/belarus')->assertForbidden();
        $this->postJson('/api/checkpoints/scrape-all')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
    }

    public function test_shared_endpoint_is_open_for_any_page_that_uses_it(): void
    {
        $this->actingAs($this->userWith(['page.zitic-converter']));

        // /api/zitic/files'ni Zitic, Zitic Converter va Zanjeer sahifalari ishlatadi
        $this->get('/api/zitic/files')->assertOk();
        $this->get('/zitic')->assertForbidden();
    }

    public function test_dashboard_menu_shows_only_permitted_links(): void
    {
        $this->actingAs($this->userWith(['page.zitic']));

        $this->get('/')->assertOk()->assertSee('href="/zitic"', false)->assertDontSee('href="/belarus"', false)
            ->assertDontSee('Foydalanuvchilar');
    }

    public function test_super_admin_has_every_permission(): void
    {
        $this->actingAs($this->superAdmin);

        $this->get('/belarus')->assertOk();
        $this->get('/admin/users')->assertOk();
        $this->get('/admin/roles')->assertOk();
    }

    // --- rollar CRUD ---

    public function test_role_crud(): void
    {
        $this->actingAs($this->superAdmin);

        $this->post('/admin/roles', ['name' => 'operator', 'permissions' => ['page.zitic', 'page.turkey']])
            ->assertRedirect('/admin/roles');
        $role = Role::findByName('operator');
        $this->assertEqualsCanonicalizing(['page.zitic', 'page.turkey'], $role->permissions->pluck('name')->all());

        $this->get('/admin/roles/create')->assertOk()->assertSee('Zitic Scraper');
        $this->get("/admin/roles/{$role->id}/edit")->assertOk()->assertSee('value="page.zitic"', false);

        $this->put("/admin/roles/{$role->id}", ['name' => 'operator2', 'permissions' => ['page.qozoq']])
            ->assertRedirect('/admin/roles');
        $this->assertEquals(['page.qozoq'], $role->fresh()->permissions->pluck('name')->all());

        $this->delete("/admin/roles/{$role->id}")->assertRedirect('/admin/roles');
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_role_validation(): void
    {
        $this->actingAs($this->superAdmin);

        $this->post('/admin/roles', ['name' => 'x', 'permissions' => ['page.fake']])->assertSessionHasErrors('permissions.0');
        $this->post('/admin/roles', ['name' => 'super_admin'])->assertSessionHasErrors('name');
    }

    public function test_super_admin_role_is_locked(): void
    {
        $this->actingAs($this->superAdmin);
        $role = Role::findByName('super_admin');

        $this->get("/admin/roles/{$role->id}/edit")->assertRedirect('/admin/roles');
        $this->put("/admin/roles/{$role->id}", ['name' => 'hacked'])->assertSessionHasErrors('role');
        $this->delete("/admin/roles/{$role->id}")->assertSessionHasErrors('role');
        $this->assertDatabaseHas('roles', ['name' => 'super_admin']);
    }

    public function test_role_with_users_cannot_be_deleted(): void
    {
        $this->actingAs($this->superAdmin);
        $user = $this->userWith(['page.zitic']);
        $role = $user->roles->first();

        $this->delete("/admin/roles/{$role->id}")->assertSessionHasErrors('role');
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    // --- foydalanuvchilar CRUD ---

    public function test_user_crud_and_role_assignment(): void
    {
        $this->actingAs($this->superAdmin);
        Role::create(['name' => 'operator', 'guard_name' => 'web'])->syncPermissions(['page.zitic']);

        $this->post('/admin/users', [
            'name' => 'Ali', 'email' => 'ali@test.uz',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
            'roles' => ['operator'],
        ])->assertRedirect('/admin/users');

        $ali = User::where('email', 'ali@test.uz')->firstOrFail();
        $this->get("/admin/users/{$ali->id}/edit")->assertOk()->assertSee('ali@test.uz');
        $this->get('/admin/users?q=ali')->assertOk()->assertSee('ali@test.uz')->assertDontSee('super@test.uz');
        $this->assertTrue($ali->hasRole('operator'));
        $this->assertTrue($ali->can('page.zitic'));

        // parol bo'sh qoldirilsa o'zgarmaydi
        $this->put("/admin/users/{$ali->id}", ['name' => 'Ali V', 'email' => 'ali@test.uz', 'roles' => []])
            ->assertRedirect('/admin/users');
        $ali->refresh();
        $this->assertSame('Ali V', $ali->name);
        $this->assertTrue(Hash::check('secret123', $ali->password));
        $this->assertFalse($ali->hasRole('operator'));

        $this->delete("/admin/users/{$ali->id}")->assertRedirect('/admin/users');
        $this->assertDatabaseMissing('users', ['id' => $ali->id]);
    }

    public function test_user_validation(): void
    {
        $this->actingAs($this->superAdmin);

        $this->post('/admin/users', [
            'name' => 'A', 'email' => 'super@test.uz', 'password' => 'short', 'password_confirmation' => 'other',
        ])->assertSessionHasErrors(['email', 'password']);
    }

    // --- huquqni oshirib olishdan himoya ---

    public function test_non_super_admin_cannot_grant_super_admin(): void
    {
        $admin = $this->userWith(['admin.users'], 'admin@test.uz');
        $this->actingAs($admin);

        $this->post('/admin/users', [
            'name' => 'X', 'email' => 'x@test.uz', 'password' => 'secret123', 'password_confirmation' => 'secret123',
            'roles' => ['super_admin'],
        ])->assertSessionHasErrors('roles.0');
        $this->assertDatabaseMissing('users', ['email' => 'x@test.uz']);

        // o'zini ham super_admin qila olmaydi
        $this->put("/admin/users/{$admin->id}", ['name' => 'A', 'email' => 'admin@test.uz', 'roles' => ['super_admin']])
            ->assertSessionHasErrors('roles.0');
        $this->assertFalse($admin->fresh()->hasRole('super_admin'));

        $this->get('/admin/users/create')->assertOk()->assertDontSee('value="super_admin"', false);
    }

    public function test_non_super_admin_cannot_touch_super_admin_user(): void
    {
        $this->actingAs($this->userWith(['admin.users'], 'admin@test.uz'));
        $id = $this->superAdmin->id;

        $this->get("/admin/users/{$id}/edit")->assertRedirect('/admin/users');
        $this->put("/admin/users/{$id}", [
            'name' => 'S', 'email' => 'super@test.uz', 'password' => 'takeover1', 'password_confirmation' => 'takeover1',
        ])->assertSessionHasErrors('user');
        $this->assertTrue(Hash::check('password', $this->superAdmin->fresh()->password));

        $this->delete("/admin/users/{$id}")->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $id]);
    }

    public function test_cannot_delete_self(): void
    {
        $this->actingAs($this->superAdmin);

        $this->delete("/admin/users/{$this->superAdmin->id}")->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $this->superAdmin->id]);
    }

    public function test_last_super_admin_keeps_role(): void
    {
        $this->actingAs($this->superAdmin);

        $this->put("/admin/users/{$this->superAdmin->id}", ['name' => 'S', 'email' => 'super@test.uz', 'roles' => []])
            ->assertSessionHasErrors('roles');
        $this->assertTrue($this->superAdmin->fresh()->hasRole('super_admin'));

        // ikkinchi super_admin bo'lsa, birinchisidan rolni olish mumkin
        $second = User::factory()->create();
        $second->assignRole('super_admin');

        $this->put("/admin/users/{$this->superAdmin->id}", ['name' => 'S', 'email' => 'super@test.uz', 'roles' => []])
            ->assertRedirect('/admin/users');
        $this->assertFalse($this->superAdmin->fresh()->hasRole('super_admin'));
    }
}
