<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Deteksi otomatis tiap menit, seirama dengan sensor yang mengirim data tiap menit.
// Di Windows/lab jalankan: php artisan schedule:work
Schedule::command('benteng:deteksi')->everyMinute()->withoutOverlapping();

// Mode demo di hosting: data simulasi dibuat ulang tiap jam agar grafik dan waktu tetap baru.
// Butuh cron `* * * * * php artisan schedule:run` di server.
Schedule::command('benteng:siapkan-demo')->hourly()->when(fn (): bool => (bool) config('benteng.mode_demo'));
