<?php

namespace App\Console\Commands;

use App\Http\Controllers\CommandController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Mode demo: meniru sensor BTG-02 tanpa perangkat sungguhan, agar juri bisa mencoba
 * karantina dan blokir domain hanya dengan server. Perintah diterapkan setelah jeda
 * singkat, lewat jalur pencatatan yang sama dengan sensor asli. Detektor juga
 * dijalankan paling banyak sekali per menit, sehingga tidak perlu schedule:work.
 *
 * Dipanggil otomatis setiap halaman dashboard dimuat bila BENTENG_MODE_DEMO=true.
 */
class SensorTiruanCommand extends Command
{
    public const SENSOR = 'BTG-02';

    /**
     * Jeda sebelum perintah "diterapkan", supaya status "Menunggu sensor" sempat terlihat.
     */
    public const JEDA_DETIK = 2;

    protected $signature = 'benteng:sensor-tiruan';

    protected $description = 'Mode demo: tiru sensor yang menerapkan perintah karantina dan blokir';

    public function handle(): int
    {
        if (! config('benteng.mode_demo')) {
            $this->error('Sensor tiruan hanya berjalan bila BENTENG_MODE_DEMO=true.');

            return self::FAILURE;
        }

        Cache::forever(CommandController::kunciDetak(self::SENSOR), now()->toDateTimeString());

        $batas = now()->subSeconds(self::JEDA_DETIK);

        DB::table('commands')
            ->where('status', 'pending')
            ->where('created_at', '<=', $batas)
            ->orderBy('id')
            ->get()
            ->each(fn ($cmd) => CommandController::selesaikanPerintah($cmd, 'ok'));

        DB::table('domain_diblokir')
            ->whereNull('diterapkan_pada')
            ->where('created_at', '<=', $batas)
            ->get()
            ->groupBy('sensor')
            ->each(fn ($daftar, $sensor) => CommandController::terapkanBlokir($sensor, (int) $daftar->max('id')));

        if (Cache::add('benteng:demo:deteksi', true, 60)) {
            $this->callSilently('benteng:deteksi');
        }

        return self::SUCCESS;
    }
}
