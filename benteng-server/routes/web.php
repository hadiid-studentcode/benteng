<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;

Route::get('/dashboard', [DashboardController::class, 'index'])->name('benteng.dashboard');
Route::post('/dashboard/incidents/{id}/karantina', [DashboardController::class, 'karantina']);
Route::post('/dashboard/incidents/{id}/false-positive', [DashboardController::class, 'falsePositive']);
Route::post('/dashboard/incidents/{id}/lepas', [DashboardController::class, 'lepas']);