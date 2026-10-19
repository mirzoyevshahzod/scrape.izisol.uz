<?php

namespace App\Providers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // super_admin barcha permission'larga avtomatik ega
        Gate::before(fn ($user) => $user->hasRole('super_admin') ? true : null);

        // Interfeysdagi sanalar ("19 Oktabr 2026", "5 daqiqa avval") lotin
        // o'zbek tilida. format('Y-m-d') kabi oddiy formatlarga ta'sir qilmaydi.
        Carbon::setLocale('uz_Latn');
    }
}
