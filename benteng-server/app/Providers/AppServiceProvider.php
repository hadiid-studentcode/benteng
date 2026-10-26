<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
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

        // Angka badge di sidebar dan jam "Diperbarui", juga dikirim ulang saat isi halaman disegarkan.
        View::composer(['benteng.layout', 'benteng.partials.segar'], function ($view) {
            $view->with('lencana', once(fn () => [
                'insiden' => DB::table('incidents')->where('status', 'baru')->count(),
                'asing' => DB::table('perangkat')->where('dikenal', false)->count(),
                'waktu' => now()->format('H:i:s'),
            ]));
        });
    }
}
