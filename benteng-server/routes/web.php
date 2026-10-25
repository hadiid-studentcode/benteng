<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SesiController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [SesiController::class, 'create'])->name('login');
    Route::post('/login', [SesiController::class, 'store'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [SesiController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('benteng.dashboard');

    // Aksi yang mengubah keadaan hanya untuk peran analis.
    Route::middleware('can:bertindak')->group(function () {
        Route::post('/dashboard/incidents/{id}/karantina', [DashboardController::class, 'karantina'])->whereNumber('id')->name('benteng.karantina');
        Route::post('/dashboard/incidents/{id}/false-positive', [DashboardController::class, 'falsePositive'])->whereNumber('id')->name('benteng.false-positive');
        Route::post('/dashboard/incidents/{id}/lepas', [DashboardController::class, 'lepas'])->whereNumber('id')->name('benteng.lepas');
    });
});
