<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Rollar CRUD (permission: admin.roles).
 *
 * `super_admin` roli tahrirlanmaydi va o'chirilmaydi — u Gate::before
 * orqali barcha ruxsatlarga ega, permission'lari ahamiyatsiz.
 */
class RoleController extends Controller
{
    private const SUPER_ADMIN = 'super_admin';

    public function index(): View
    {
        $roles = Role::withCount(['permissions', 'users'])->orderBy('name')->get();

        return view('admin.roles.index', compact('roles'));
    }

    public function create(): View
    {
        return view('admin.roles.form', [
            'role' => new Role(),
            'groups' => Permissions::GROUPS,
            'selected' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()->route('admin.roles.index')->with('status', "\"{$role->name}\" roli yaratildi.");
    }

    public function edit(Role $role): View|RedirectResponse
    {
        if ($role->name === self::SUPER_ADMIN) {
            return $this->superAdminLocked();
        }

        return view('admin.roles.form', [
            'role' => $role,
            'groups' => Permissions::GROUPS,
            'selected' => $role->permissions->pluck('name')->all(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if ($role->name === self::SUPER_ADMIN) {
            return $this->superAdminLocked();
        }

        $data = $this->validated($request, $role);

        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()->route('admin.roles.index')->with('status', "\"{$role->name}\" roli saqlandi.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->name === self::SUPER_ADMIN) {
            return $this->superAdminLocked();
        }

        $usersCount = $role->users()->count();

        if ($usersCount > 0) {
            return back()->withErrors(['role' => "\"{$role->name}\" rolida {$usersCount} ta foydalanuvchi bor. Avval ulardan bu rolni olib tashlang."]);
        }

        $role->delete();

        return redirect()->route('admin.roles.index')->with('status', "\"{$role->name}\" roli o'chirildi.");
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:50', 'regex:/^[\pL\pN _-]+$/u',
                Rule::notIn([self::SUPER_ADMIN]),
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role?->id),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(array_keys(Permissions::all()))],
        ], [
            'name.regex' => 'Rol nomida faqat harf, raqam, probel, "-" va "_" bo\'lishi mumkin.',
            'name.not_in' => '"super_admin" nomi tizim uchun band.',
            'name.unique' => 'Bunday nomli rol allaqachon bor.',
        ]);
    }

    private function superAdminLocked(): RedirectResponse
    {
        return redirect()->route('admin.roles.index')
            ->withErrors(['role' => 'super_admin roli tizim roli — u barcha ruxsatlarga avtomatik ega, uni o\'zgartirib yoki o\'chirib bo\'lmaydi.']);
    }
}
