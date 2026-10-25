<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', [DashboardController::class, 'index'])->name('benteng.dashboard');
Route::post('/dashboard/incidents/{id}/karantina', [DashboardController::class, 'karantina'])->whereNumber('id')->name('benteng.karantina');
Route::post('/dashboard/incidents/{id}/false-positive', [DashboardController::class, 'falsePositive'])->whereNumber('id')->name('benteng.false-positive');
Route::post('/dashboard/incidents/{id}/lepas', [DashboardController::class, 'lepas'])->whereNumber('id')->name('benteng.lepas');
