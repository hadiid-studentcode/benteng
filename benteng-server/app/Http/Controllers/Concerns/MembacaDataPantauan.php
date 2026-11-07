<?php

namespace App\Http\Controllers\Concerns;

use App\Console\Commands\DeteksiCommand;
use App\Http\Controllers\CommandController;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Query baca yang dipakai bersama oleh halaman-halaman dashboard.
 */
trait MembacaDataPantauan
{
    /**
     * Tampilkan halaman penuh, atau hanya fragmen "isi" bila diminta skrip
     * penyegaran otomatis lewat header X-Benteng-Sebagian.
     *
     * @param  array<string, mixed>  $data
     */
    protected function tampilkan(Request $request, string $halaman, array $data): View|string
    {
        /** @var View $view */
        $view = view($halaman, $data);

        return $view->fragmentIf($request->hasHeader('X-Benteng-Sebagian'), 'isi');
    }

    /**
     * Insiden aktif di atas, lalu yang dikarantina, lalu yang selesai.
     */
    protected function insidenUrut(): Builder
    {
        return DB::table('incidents')
            ->orderByRaw("case status when 'baru' then 0 when 'dikarantina' then 1 else 2 end")
            ->orderByDesc('skor')
            ->orderByDesc('id');
    }

    /**
     * Lengkapi insiden dengan perintah terakhir ke sensor dan data perangkatnya.
     *
     * @param  Collection<int, object>  $incidents
     * @param  Collection<int, object>  $perangkat
     * @return Collection<int, object>
     */
    protected function lengkapiInsiden(Collection $incidents, Collection $perangkat): Collection
    {
        $perintahTerakhir = $this->perintahTerakhirPerPerangkat();
        $perangkatPerKunci = $perangkat->keyBy(fn ($p) => $p->sensor.'|'.$p->ip);

        return $incidents->each(function ($i) use ($perintahTerakhir, $perangkatPerKunci) {
            $i->perintah = $perintahTerakhir->get($i->sensor.'|'.$i->client_ip);
            $i->perangkat = $perangkatPerKunci->get($i->sensor.'|'.$i->client_ip);
        });
    }

    /**
     * Daftar sensor beserta detak terakhir responder, data DNS dan laporan DHCP terakhir.
     *
     * @return Collection<int, array{nama: string, detak: ?Carbon, online: bool, dataTerakhir: ?Carbon, dhcpTerakhir: ?Carbon, info: array<string, string>}>
     */
    protected function statusSensor(Carbon $sekarang): Collection
    {
        $dataTerakhir = DB::table('dns_events')
            ->selectRaw('sensor, MAX(created_at) as terakhir')
            ->groupBy('sensor')
            ->get()
            ->pluck('terakhir', 'sensor');

        $dhcpTerakhir = DB::table('perangkat')
            ->selectRaw('sensor, MAX(terakhir_terlihat) as terakhir')
            ->groupBy('sensor')
            ->get()
            ->pluck('terakhir', 'sensor');

        $nama = $dataTerakhir->keys()
            ->merge($dhcpTerakhir->keys())
            ->merge(DB::table('commands')->distinct()->pluck('sensor'))
            ->unique()
            ->sort()
            ->values();

        $batasOnline = $sekarang->copy()->subSeconds((int) config('benteng.responder_online_detik'));

        return $nama->map(function (string $s) use ($dataTerakhir, $dhcpTerakhir, $batasOnline) {
            $detak = Cache::get(CommandController::kunciDetak($s));
            $detak = $detak ? Carbon::parse($detak) : null;

            return [
                'nama' => $s,
                'detak' => $detak,
                'online' => $detak !== null && $detak->greaterThanOrEqualTo($batasOnline),
                'dataTerakhir' => isset($dataTerakhir[$s]) ? Carbon::parse($dataTerakhir[$s]) : null,
                'dhcpTerakhir' => isset($dhcpTerakhir[$s]) ? Carbon::parse($dhcpTerakhir[$s]) : null,
                'info' => config('benteng.sensor_info')[$s] ?? [],
            ];
        });
    }

    /**
     * @return array{detak: ?Carbon, jalan: bool}
     */
    protected function statusDetektor(Carbon $sekarang): array
    {
        $detak = Cache::get(DeteksiCommand::KUNCI_DETAK);
        $detak = $detak ? Carbon::parse($detak) : null;

        return [
            'detak' => $detak,
            'jalan' => $detak !== null && $detak->greaterThanOrEqualTo($sekarang->copy()->subSeconds((int) config('benteng.detektor_online_detik'))),
        ];
    }

    /**
     * Status tiap perangkat ("sensor|ip"): dikarantina > insiden > asing.
     *
     * @return Collection<string, string>
     */
    protected function statusPerangkat(): Collection
    {
        return DB::table('incidents')
            ->whereIn('status', ['baru', 'dikarantina'])
            ->get(['sensor', 'client_ip', 'status', 'jenis'])
            ->groupBy(fn ($i) => $i->sensor.'|'.$i->client_ip)
            ->map(fn (Collection $grup) => match (true) {
                $grup->contains('status', 'dikarantina') => 'dikarantina',
                $grup->contains(fn ($i) => $i->jenis !== 'perangkat_asing') => 'insiden',
                default => 'asing',
            });
    }

    /**
     * Gabungan perangkat yang terlihat di DNS dan di DHCP, dikunci per sensor+IP.
     *
     * @return Collection<int, object>
     */
    protected function daftarPerangkat(): Collection
    {
        $statusPerangkat = $this->statusPerangkat();
        $daftar = [];

        $dns = DB::table('dns_events')
            ->select('client_ip', 'sensor', DB::raw('COUNT(*) as jumlah'), DB::raw('COUNT(DISTINCT domain) as domain_unik'),
                DB::raw('MIN(queried_at) as awal'), DB::raw('MAX(queried_at) as akhir'))
            ->groupBy('client_ip', 'sensor')
            ->get();

        foreach ($dns as $d) {
            // 127.0.0.1 / ::1 adalah sensor itu sendiri (mis. sinkronisasi NTP), bukan perangkat jaringan.
            if (! filter_var($d->client_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE)) {
                continue;
            }
            $daftar[$d->sensor.'|'.$d->client_ip] = (object) [
                'sensor' => $d->sensor, 'ip' => $d->client_ip, 'jumlah' => (int) $d->jumlah,
                'domain_unik' => (int) $d->domain_unik, 'awal' => $d->awal, 'akhir' => $d->akhir,
                'mac' => null, 'hostname' => null, 'dikenal' => null,
            ];
        }

        // Lease terbaru per IP dipakai untuk MAC dan nama host.
        foreach (DB::table('perangkat')->orderByDesc('terakhir_terlihat')->get() as $p) {
            $kunci = $p->sensor.'|'.$p->ip;
            $daftar[$kunci] ??= (object) [
                'sensor' => $p->sensor, 'ip' => $p->ip, 'jumlah' => 0, 'domain_unik' => 0,
                'awal' => $p->pertama_terlihat, 'akhir' => $p->terakhir_terlihat,
                'mac' => null, 'hostname' => null, 'dikenal' => null,
            ];
            if ($daftar[$kunci]->mac === null) {
                $daftar[$kunci]->mac = $p->mac;
                $daftar[$kunci]->hostname = $p->hostname;
                $daftar[$kunci]->dikenal = (bool) $p->dikenal;
            }
        }

        foreach ($daftar as $kunci => $p) {
            $p->status = $statusPerangkat->get($kunci) ?? ($p->dikenal === false ? 'asing' : 'normal');
        }

        return collect($daftar)->sortByDesc('jumlah')->values();
    }

    /**
     * Data diagram topologi: tiap sensor dengan perangkat di bawahnya.
     *
     * @param  Collection<int, array<string, mixed>>  $sensor
     * @param  Collection<int, object>  $perangkat
     * @return Collection<int, array<string, mixed>>
     */
    protected function dataTopologi(Collection $sensor, Collection $perangkat, int $batasPerSensor = 10): Collection
    {
        return $sensor->map(fn (array $s) => [
            ...$s,
            'perangkat' => $perangkat->where('sensor', $s['nama'])->take($batasPerSensor)->values(),
            'lainnya' => max(0, $perangkat->where('sensor', $s['nama'])->count() - $batasPerSensor),
        ]);
    }

    /**
     * Median selisih detik antara kolom "awal" dan "akhir", atau null bila tidak ada data.
     *
     * @param  Collection<int, object>  $baris
     */
    protected function medianDetik(Collection $baris): ?int
    {
        $selisih = $baris
            ->filter(fn ($b) => $b->awal && $b->akhir)
            ->map(fn ($b) => strtotime($b->akhir) - strtotime($b->awal))
            ->filter(fn ($d) => $d >= 0);

        return $selisih->isEmpty() ? null : (int) round($selisih->median());
    }

    /**
     * Perintah terbaru untuk tiap perangkat, dikunci "sensor|ip".
     *
     * @return Collection<string, object>
     */
    protected function perintahTerakhirPerPerangkat(): Collection
    {
        return DB::table('commands')
            ->whereIn('id', DB::table('commands')->selectRaw('MAX(id)')->groupBy('sensor', 'target_ip'))
            ->get()
            ->keyBy(fn ($c) => $c->sensor.'|'.$c->target_ip);
    }

    /**
     * Domain tujuan beacon. Sidik beacon berbentuk "beacon|sensor|client|domain".
     */
    protected function domainBeacon(object $insiden): ?string
    {
        return $insiden->jenis === 'beaconing' ? (explode('|', $insiden->sidik, 4)[3] ?? null) : null;
    }

    /**
     * Query ke domain (termasuk subdomainnya) sejak waktu tertentu, mis. sejak domain diblokir.
     */
    protected function queryKeDomain(string $sensor, string $domain, string $sejak): Builder
    {
        return DB::table('dns_events')
            ->where('sensor', $sensor)
            ->where('queried_at', '>=', $sejak)
            ->where(fn ($q) => $q->where('domain', $domain)->orWhere('domain', 'like', "%.$domain"));
    }

    /**
     * Waktu query (unix detik) ke domain beacon, untuk digambar sebagai bukti keteraturan.
     *
     * @return array<int, int>
     */
    protected function ketukanBeacon(object $insiden, string $domain): array
    {
        return DB::table('dns_events')
            ->where('sensor', $insiden->sensor)
            ->where('client_ip', $insiden->client_ip)
            ->where('domain', $domain)
            ->orderByDesc('queried_at')
            ->limit(120)
            ->pluck('queried_at')
            ->map(fn ($t) => strtotime(substr($t, 0, 19)))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Jumlah query DNS per menit untuk grafik, termasuk menit yang kosong.
     *
     * @return array<int, array{menit: string, jumlah: int}>
     */
    protected function aktivitasPerMenit(Carbon $sekarang, int $menit = 60): array
    {
        $awal = $sekarang->copy()->startOfMinute()->subMinutes($menit - 1);

        // queried_at disimpan sebagai teks "Y-m-d H:i:s"; 16 karakter pertama = menitnya.
        $perMenit = DB::table('dns_events')
            ->selectRaw('substr(queried_at, 1, 16) as menit, COUNT(*) as jumlah')
            ->where('queried_at', '>=', $awal)
            ->groupBy('menit')
            ->get()
            ->pluck('jumlah', 'menit');

        $hasil = [];
        for ($m = 0; $m < $menit; $m++) {
            $waktu = $awal->copy()->addMinutes($m);
            $hasil[] = [
                'menit' => $waktu->format('H:i'),
                'jumlah' => (int) ($perMenit[$waktu->format('Y-m-d H:i')] ?? 0),
            ];
        }

        return $hasil;
    }
}
