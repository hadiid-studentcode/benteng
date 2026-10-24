<?php
// Tempelkan dua baris di bawah ke routes/api.php (bukan file terpisah).

use App\Http\Controllers\IngestController;

Route::post('/ingest/dns', [IngestController::class, 'dns']);
