<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * config/navigation.php'dagi menyuni foydalanuvchi ruxsatlari bo'yicha
 * filtrlaydi va har bir bandga url/faollik holatini qo'shadi.
 */
class Navigation
{
    /**
     * @return array<int,array{title:?string,items:array<int,array>}> bo'sh guruhlarsiz
     */
    public static function for(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $groups = [];

        foreach (config('navigation') as $group) {
            $items = [];

            foreach ($group['items'] as $item) {
                if (! Route::has($item['route']) || ! self::allowed($user, $item['permission'] ?? null)) {
                    continue;
                }

                $items[] = $item + [
                    'url' => route($item['route']),
                    'active' => request()->routeIs($item['match'] ?? $item['route']),
                ];
            }

            if ($items) {
                $groups[] = ['title' => $group['title'], 'items' => $items];
            }
        }

        return $groups;
    }

    private static function allowed(User $user, string|array|null $permission): bool
    {
        if ($permission === null) {
            return true;
        }

        return collect((array) $permission)->contains(fn ($p) => $user->can($p));
    }
}
