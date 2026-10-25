<?php

use App\Http\Controllers\CommandController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\IngestController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('/ingest/dns', [IngestController::class, 'dns']);




Route::post('/ingest/dns', [IngestController::class, 'dns']);

Route::get('/sensors/{sensor}/commands', [CommandController::class, 'ambil']);
Route::post('/sensors/{sensor}/commands/{id}/ack', [CommandController::class, 'ack']);