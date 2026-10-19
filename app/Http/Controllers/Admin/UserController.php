<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Foydalanuvchilar CRUD va rol biriktirish (permission: admin.users).
 *
 * Huquqni oshirib olishning oldini olish uchun:
 *  - super_admin rolini faqat super_admin bera oladi / olib tashlay oladi;
 *  - super_admin foydalanuvchini faqat super_admin tahrirlay / o'chira oladi
 *    (aks holda uning parolini o'zgartirib, akkauntini egallash mumkin edi);
 *  - oxirgi super_admin'ni o'chirib yoki undan rolni olib bo'lmaydi;
 *  - foydalanuvchi o'zini o'zi o'chira olmaydi.
 */
class UserController extends Controller
{
    private const SUPER_ADMIN = 'super_admin';

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $users = User::with('roles')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'search'));
    }

    public function create(Request $request): View
    {
        return view('admin.users.form', [
            'user' => new User(),
            'roles' => $this->assignableRoles($request->user()),
            'selected' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $user->syncRoles($data['roles'] ?? []);

        return redirect()->route('admin.users.index')->with('status', "{$user->email} yaratildi.");
    }

    public function edit(Request $request, User $user): View|RedirectResponse
    {
        if ($denied = $this->denyIfProtected($request->user(), $user)) {
            return $denied;
        }

        return view('admin.users.form', [
            'user' => $user,
            'roles' => $this->assignableRoles($request->user()),
            'selected' => $user->roles->pluck('name')->all(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if ($denied = $this->denyIfProtected($request->user(), $user)) {
            return $denied;
        }

        $data = $this->validated($request, $user);
        $roles = $data['roles'] ?? [];

        if ($user->hasRole(self::SUPER_ADMIN) && ! in_array(self::SUPER_ADMIN, $roles, true) && $this->superAdminCount() <= 1) {
            return back()->withInput()->withErrors(['roles' => 'Bu oxirgi super_admin — undan super_admin rolini olib bo\'lmaydi.']);
        }

        $user->fill(['name' => $data['name'], 'email' => $data['email']]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();
        $user->syncRoles($roles);

        return redirect()->route('admin.users.index')->with('status', "{$user->email} saqlandi.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'O\'zingizni o\'chira olmaysiz.']);
        }

        if ($denied = $this->denyIfProtected($request->user(), $user)) {
            return $denied;
        }

        if ($user->hasRole(self::SUPER_ADMIN) && $this->superAdminCount() <= 1) {
            return back()->withErrors(['user' => 'Oxirgi super_admin\'ni o\'chirib bo\'lmaydi.']);
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', "{$user->email} o'chirildi.");
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $assignable = $this->assignableRoles($request->user())->pluck('name')->all();

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::in($assignable)],
        ], [
            'email.unique' => 'Bu email bilan foydalanuvchi allaqachon bor.',
            'password.confirmed' => 'Parollar mos kelmadi.',
            'roles.*.in' => 'Bu rolni biriktirishga ruxsatingiz yo\'q.',
        ]);
    }

    /** super_admin bo'lmagan admin super_admin rolini ko'rmaydi va bera olmaydi */
    private function assignableRoles(User $actor)
    {
        return Role::where('guard_name', 'web')
            ->when(! $actor->hasRole(self::SUPER_ADMIN), fn ($q) => $q->where('name', '!=', self::SUPER_ADMIN))
            ->orderBy('name')
            ->get();
    }

    private function denyIfProtected(User $actor, User $target): ?RedirectResponse
    {
        if ($target->hasRole(self::SUPER_ADMIN) && ! $actor->hasRole(self::SUPER_ADMIN)) {
            return redirect()->route('admin.users.index')
                ->withErrors(['user' => 'super_admin foydalanuvchini faqat super_admin o\'zgartira oladi.']);
        }

        return null;
    }

    private function superAdminCount(): int
    {
        return User::role(self::SUPER_ADMIN)->count();
    }
}
