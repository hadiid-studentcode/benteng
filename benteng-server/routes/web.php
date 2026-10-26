<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InsidenController;
use App\Http\Controllers\PerangkatController;
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
    Route::get('/topologi', [DashboardController::class, 'topologi'])->name('benteng.topologi');
    Route::get('/sensor', [DashboardController::class, 'sensor'])->name('benteng.sensor');
    Route::get('/insiden', [InsidenController::class, 'index'])->name('benteng.insiden');
    Route::get('/insiden/{id}', [InsidenController::class, 'show'])->whereNumber('id')->name('benteng.insiden.detail');
    Route::get('/perangkat', [PerangkatController::class, 'index'])->name('benteng.perangkat');
    Route::get('/audit', [AuditController::class, 'index'])->name('benteng.audit');

    // Tindakan yang mengubah keadaan hanya untuk peran analis.
    Route::middleware('can:bertindak')->group(function () {
        Route::post('/insiden/{id}/karantina', [InsidenController::class, 'karantina'])->whereNumber('id')->name('benteng.karantina');
        Route::post('/insiden/{id}/false-positive', [InsidenController::class, 'falsePositive'])->whereNumber('id')->name('benteng.false-positive');
        Route::post('/insiden/{id}/lepas', [InsidenController::class, 'lepas'])->whereNumber('id')->name('benteng.lepas');
    });
});
