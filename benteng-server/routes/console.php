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
