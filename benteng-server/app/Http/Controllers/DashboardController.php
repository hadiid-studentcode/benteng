<?php

namespace App\Http\Controllers;

use App\Console\Commands\DeteksiCommand;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Jumlah menit yang ditampilkan di grafik aktivitas DNS.
     */
    private const MENIT_GRAFIK = 60;

    /**
     * Jumlah perangkat per sensor yang digambar di diagram topologi.
     */
    private const PERANGKAT_TOPOLOGI = 10;

    /**
     * Dashboard analis. Bila diminta dengan header X-Benteng-Sebagian, hanya
     * fragmen "isi" yang dikirim; dipakai skrip penyegar otomatis di halaman.
     */
    public function index(Request $request): View|string
    {
        $sekarang = Carbon::now();
        $batas24 = $sekarang->copy()->subDay();

        $sensor = $this->statusSensor($sekarang);
        $detektor = $this->statusDetektor($sekarang);

        // Insiden aktif di atas, lalu yang dikarantina, lalu false positive.
        $incidents = DB::table('incidents')
            ->orderByRaw("case status when 'baru' then 0 when 'dikarantina' then 1 else 2 end")
            ->orderByDesc('skor')
            ->orderByDesc('id')
            ->get();

        $perintahTerakhir = $this->perintahTerakhirPerPerangkat();

        foreach ($incidents as $i) {
            // Sidik beacon berbentuk "beacon|sensor|client|domain".
            $i->domain = $i->jenis === 'beaconing' ? (explode('|', $i->sidik, 4)[3] ?? null) : null;
            $i->ketukan = $i->domain ? $this->ketukanBeacon($i) : [];
            $i->perintah = $perintahTerakhir->get($i->sensor.'|'.$i->client_ip);
        }

        $perangkat = $this->daftarPerangkat($this->statusPerangkat());

        $metrik = [
            'sensorOnline' => $sensor->where('online', true)->count(),
            'sensorTotal' => $sensor->count(),
            'perangkat' => $perangkat->count(),
            'asing' => $perangkat->where('status', 'asing')->count(),
            'query24' => DB::table('dns_events')->where('queried_at', '>=', $batas24)->count(),
            'insidenAktif' => DB::table('incidents')->where('status', 'baru')->count(),
            'dikarantina' => DB::table('incidents')->where('status', 'dikarantina')->distinct()->count('client_ip'),
            'waktuDeteksi' => $this->medianDetik(DB::table('incidents')->whereNotNull('mulai_pada')->get(['mulai_pada as awal', 'created_at as akhir'])),
            'responsSensor' => $this->medianDetik(DB::table('commands')->where('status', 'selesai')->get(['created_at as awal', 'selesai_pada as akhir'])),
        ];

        $topologi = $sensor->map(fn (array $s) => [
            ...$s,
            'info' => config('benteng.sensor_info')[$s['nama']] ?? [],
            'perangkat' => $perangkat->where('sensor', $s['nama'])->take(self::PERANGKAT_TOPOLOGI)->values(),
            'lainnya' => max(0, $perangkat->where('sensor', $s['nama'])->count() - self::PERANGKAT_TOPOLOGI),
        ]);

        $audit = DB::table('audit_logs')
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        $aktivitas = $this->aktivitasPerMenit($sekarang);
        $bisaBertindak = $request->user()->can('bertindak');

        /** @var View $view */
        $view = view('benteng.dashboard', compact(
            'sekarang', 'sensor', 'detektor', 'metrik', 'incidents', 'perangkat', 'topologi', 'audit', 'aktivitas', 'bisaBertindak'
        ));

        return $view->fragmentIf($request->hasHeader('X-Benteng-Sebagian'), 'isi');
    }

    /**
     * Analis menekan "Karantina": buat perintah untuk sensor dan tandai semua
     * insiden aktif perangkat itu, karena karantina berlaku per perangkat.
     */
    public function karantina(Request $request, int $id): RedirectResponse
    {
        $insiden = DB::table('incidents')->find($id);
        if (! $insiden || $insiden->status !== 'baru') {
            return back()->with('galat', 'Insiden tidak ditemukan atau sudah ditangani.');
        }

        // IP berasal dari data insiden yang IP-nya sudah divalidasi saat ingest.
        if (! filter_var($insiden->client_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return back()->with('galat', "IP {$insiden->client_ip} tidak bisa dikarantina (hanya IPv4).");
        }

        DB::transaction(function () use ($insiden, $request) {
            $this->kirimPerintah($insiden, 'quarantine');

            DB::table('incidents')
                ->where('sensor', $insiden->sensor)
                ->where('client_ip', $insiden->client_ip)
                ->where('status', 'baru')
                ->update(['status' => 'dikarantina', 'updated_at' => now()]);

            $this->catatAudit($request->user(), $insiden, 'minta karantina');
        });

        return back()->with('pesan', "Perintah karantina {$insiden->client_ip} dikirim ke {$insiden->sensor}. Menunggu sensor menerapkan.");
    }

    /**
     * Analis menandai insiden sebagai false positive. Untuk insiden perangkat
     * asing, ini berarti perangkat didaftarkan sebagai perangkat sah satuan.
     */
    public function falsePositive(Request $request, int $id): RedirectResponse
    {
        $insiden = DB::table('incidents')->find($id);
        if (! $insiden || $insiden->status !== 'baru') {
            return back()->with('galat', 'Insiden tidak ditemukan atau sudah ditangani.');
        }

        $daftarkan = $insiden->jenis === 'perangkat_asing';

        DB::transaction(function () use ($insiden, $request, $daftarkan) {
            DB::table('incidents')->where('id', $insiden->id)->update([
                'status' => 'false_positive',
                'updated_at' => now(),
            ]);

            if ($daftarkan) {
                // Sidik perangkat asing berbentuk "asing|sensor|mac".
                $mac = explode('|', $insiden->sidik, 3)[2] ?? '';
                DB::table('perangkat')
                    ->where('sensor', $insiden->sensor)
                    ->where('mac', $mac)
                    ->update(['dikenal' => true, 'updated_at' => now()]);
            }

            $this->catatAudit($request->user(), $insiden, $daftarkan ? 'daftarkan perangkat' : 'tandai false positive');
        });

        return back()->with('pesan', $daftarkan
            ? "Perangkat {$insiden->client_ip} didaftarkan sebagai perangkat sah."
            : "Insiden #{$insiden->id} ditandai false positive.");
    }

    /**
     * Analis melepas karantina. Semua insiden perangkat yang dikarantina
     * kembali aktif agar tetap ditindaklanjuti.
     */
    public function lepas(Request $request, int $id): RedirectResponse
    {
        $insiden = DB::table('incidents')->find($id);
        if (! $insiden || $insiden->status !== 'dikarantina') {
            return back()->with('galat', 'Insiden tidak ditemukan atau tidak sedang dikarantina.');
        }

        if (! filter_var($insiden->client_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return back()->with('galat', "IP {$insiden->client_ip} tidak valid.");
        }

        DB::transaction(function () use ($insiden, $request) {
            $this->kirimPerintah($insiden, 'unquarantine');

            DB::table('incidents')
                ->where('sensor', $insiden->sensor)
                ->where('client_ip', $insiden->client_ip)
                ->where('status', 'dikarantina')
                ->update(['status' => 'baru', 'updated_at' => now()]);

            $this->catatAudit($request->user(), $insiden, 'lepas karantina');
        });

        return back()->with('pesan', "Perintah lepas karantina {$insiden->client_ip} dikirim ke {$insiden->sensor}.");
    }

    private function kirimPerintah(object $insiden, string $aksi): void
    {
        DB::table('commands')->insert([
            'sensor' => $insiden->sensor,
            'aksi' => $aksi,
            'target_ip' => $insiden->client_ip,
            'incident_id' => $insiden->id,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function catatAudit(User $user, object $insiden, string $aksi): void
    {
        DB::table('audit_logs')->insert([
            'pelaku' => mb_substr($user->name, 0, 64),
            'user_id' => $user->id,
            'aksi' => $aksi,
            'target' => $insiden->client_ip,
            'incident_id' => $insiden->id,
            'created_at' => now(),
        ]);
    }

    /**
     * Daftar sensor beserta detak terakhir responder dan data DNS terakhir.
     *
     * @return Collection<int, array{nama: string, detak: ?Carbon, online: bool, dataTerakhir: ?Carbon}>
     */
    private function statusSensor(Carbon $sekarang): Collection
    {
        $dataTerakhir = DB::table('dns_events')
            ->selectRaw('sensor, MAX(created_at) as terakhir')
            ->groupBy('sensor')
            ->get()
            ->pluck('terakhir', 'sensor');

        $nama = $dataTerakhir->keys()
            ->merge(DB::table('commands')->distinct()->pluck('sensor'))
            ->merge(DB::table('perangkat')->distinct()->pluck('sensor'))
            ->unique()
            ->sort()
            ->values();

        $batasOnline = $sekarang->copy()->subSeconds((int) config('benteng.responder_online_detik'));

        return $nama->map(function (string $s) use ($dataTerakhir, $batasOnline) {
            $detak = Cache::get(CommandController::kunciDetak($s));
            $detak = $detak ? Carbon::parse($detak) : null;

            return [
                'nama' => $s,
                'detak' => $detak,
                'online' => $detak !== null && $detak->greaterThanOrEqualTo($batasOnline),
                'dataTerakhir' => isset($dataTerakhir[$s]) ? Carbon::parse($dataTerakhir[$s]) : null,
            ];
        });
    }

    /**
     * @return array{detak: ?Carbon, jalan: bool}
     */
    private function statusDetektor(Carbon $sekarang): array
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
    private function statusPerangkat(): Collection
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
     * @param  Collection<string, string>  $statusPerangkat
     * @return Collection<int, object>
     */
    private function daftarPerangkat(Collection $statusPerangkat): Collection
    {
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
     * Median selisih detik antara kolom "awal" dan "akhir", atau null bila tidak ada data.
     *
     * @param  Collection<int, object{awal: string, akhir: string}>  $baris
     */
    private function medianDetik(Collection $baris): ?int
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
    private function perintahTerakhirPerPerangkat(): Collection
    {
        return DB::table('commands')
            ->whereIn('id', DB::table('commands')->selectRaw('MAX(id)')->groupBy('sensor', 'target_ip'))
            ->get()
            ->keyBy(fn ($c) => $c->sensor.'|'.$c->target_ip);
    }

    /**
     * Waktu query (unix detik) ke domain beacon, untuk digambar sebagai bukti keteraturan.
     *
     * @return array<int, int>
     */
    private function ketukanBeacon(object $insiden): array
    {
        return DB::table('dns_events')
            ->where('sensor', $insiden->sensor)
            ->where('client_ip', $insiden->client_ip)
            ->where('domain', $insiden->domain)
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
    private function aktivitasPerMenit(Carbon $sekarang): array
    {
        $awal = $sekarang->copy()->startOfMinute()->subMinutes(self::MENIT_GRAFIK - 1);

        // queried_at disimpan sebagai teks "Y-m-d H:i:s"; 16 karakter pertama = menitnya.
        $perMenit = DB::table('dns_events')
            ->selectRaw('substr(queried_at, 1, 16) as menit, COUNT(*) as jumlah')
            ->where('queried_at', '>=', $awal)
            ->groupBy('menit')
            ->get()
            ->pluck('jumlah', 'menit');

        $hasil = [];
        for ($m = 0; $m < self::MENIT_GRAFIK; $m++) {
            $menit = $awal->copy()->addMinutes($m);
            $hasil[] = [
                'menit' => $menit->format('H:i'),
                'jumlah' => (int) ($perMenit[$menit->format('Y-m-d H:i')] ?? 0),
            ];
        }

        return $hasil;
    }
}
