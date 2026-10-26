<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\MembacaDataPantauan;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Halaman ringkasan: dashboard, topologi jaringan, dan status sensor.
 */
class DashboardController extends Controller
{
    use MembacaDataPantauan;

    public function index(Request $request): View|string
    {
        $sekarang = Carbon::now();
        $sensor = $this->statusSensor($sekarang);
        $detektor = $this->statusDetektor($sekarang);
        $perangkat = $this->daftarPerangkat();

        $metrik = [
            'sensorOnline' => $sensor->where('online', true)->count(),
            'sensorTotal' => $sensor->count(),
            'perangkat' => $perangkat->count(),
            'asing' => $perangkat->where('status', 'asing')->count(),
            'query24' => DB::table('dns_events')->where('queried_at', '>=', $sekarang->copy()->subDay())->count(),
            'insidenAktif' => DB::table('incidents')->where('status', 'baru')->count(),
            'dikarantina' => DB::table('incidents')->where('status', 'dikarantina')->distinct()->count('client_ip'),
            'waktuDeteksi' => $this->medianDetik(DB::table('incidents')->whereNotNull('mulai_pada')->get(['mulai_pada as awal', 'created_at as akhir'])),
            'responsSensor' => $this->medianDetik(DB::table('commands')->where('status', 'selesai')->get(['created_at as awal', 'selesai_pada as akhir'])),
        ];

        return $this->tampilkan($request, 'benteng.dashboard', [
            'sekarang' => $sekarang,
            'sensor' => $sensor,
            'detektor' => $detektor,
            'metrik' => $metrik,
            'insidenTerbaru' => $this->lengkapiInsiden($this->insidenUrut()->limit(5)->get(), $perangkat),
            'aktivitas' => $this->aktivitasPerMenit($sekarang),
            'topologi' => $this->dataTopologi($sensor, $perangkat),
            'bisaBertindak' => $request->user()->can('bertindak'),
        ]);
    }

    public function topologi(Request $request): View|string
    {
        $sekarang = Carbon::now();

        return $this->tampilkan($request, 'benteng.topologi', [
            'sekarang' => $sekarang,
            'detektor' => $this->statusDetektor($sekarang),
            'topologi' => $this->dataTopologi($this->statusSensor($sekarang), $this->daftarPerangkat(), 30),
        ]);
    }

    public function sensor(Request $request): View|string
    {
        $sekarang = Carbon::now();
        $perangkat = $this->daftarPerangkat();
        $perintahMenunggu = DB::table('commands')
            ->where('status', 'pending')
            ->selectRaw('sensor, COUNT(*) as jumlah')
            ->groupBy('sensor')
            ->get()
            ->pluck('jumlah', 'sensor');

        $sensor = $this->statusSensor($sekarang)->map(fn (array $s) => [
            ...$s,
            'jumlahPerangkat' => $perangkat->where('sensor', $s['nama'])->count(),
            'perintahMenunggu' => (int) ($perintahMenunggu[$s['nama']] ?? 0),
        ]);

        return $this->tampilkan($request, 'benteng.sensor', [
            'sensor' => $sensor,
            'detektor' => $this->statusDetektor($sekarang),
        ]);
    }
}
