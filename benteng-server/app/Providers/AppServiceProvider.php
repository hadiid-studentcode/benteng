<?php

namespace App\Providers;

use App\Models\User;
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
        // Karantina, lepas, false positive, dan daftarkan perangkat hanya untuk analis.
        Gate::define('bertindak', fn (User $user) => $user->bisaBertindak());
    }
}
