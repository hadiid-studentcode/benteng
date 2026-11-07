<?php

use App\Http\Controllers\CommandController;
use App\Http\Controllers\IngestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/ingest/dns', [IngestController::class, 'dns']);
Route::post('/ingest/dhcp', [IngestController::class, 'dhcp']);

Route::get('/sensors/{sensor}/commands', [CommandController::class, 'ambil']);
Route::post('/sensors/{sensor}/commands/{id}/ack', [CommandController::class, 'ack']);
Route::get('/sensors/{sensor}/karantina', [CommandController::class, 'karantina']);
Route::get('/sensors/{sensor}/blokir', [CommandController::class, 'blokir']);
Route::post('/sensors/{sensor}/blokir/ack', [CommandController::class, 'ackBlokir']);
