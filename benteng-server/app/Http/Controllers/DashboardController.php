<?php

namespace App\Http\Controllers;

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
     * Dashboard analis. Bila diminta dengan header X-Benteng-Sebagian, hanya
     * fragmen "isi" yang dikirim; dipakai skrip penyegar otomatis di halaman.
     */
    public function index(Request $request): View|string
    {
        $sekarang = Carbon::now();
        $batas24 = $sekarang->copy()->subDay();

        $sensor = $this->statusSensor($sekarang);

        $metrik = [
            'sensorOnline' => $sensor->where('online', true)->count(),
            'sensorTotal' => $sensor->count(),
            'perangkat' => DB::table('dns_events')->distinct()->count('client_ip'),
            'query24' => DB::table('dns_events')->where('queried_at', '>=', $batas24)->count(),
            'insidenAktif' => DB::table('incidents')->where('status', 'baru')->count(),
            'dikarantina' => DB::table('incidents')->where('status', 'dikarantina')->distinct()->count('client_ip'),
        ];

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

        $perangkat = DB::table('dns_events')
            ->select('client_ip', 'sensor', DB::raw('COUNT(*) as jumlah'), DB::raw('COUNT(DISTINCT domain) as domain_unik'),
                DB::raw('MIN(queried_at) as awal'), DB::raw('MAX(queried_at) as akhir'))
            ->groupBy('client_ip', 'sensor')
            ->orderByDesc('jumlah')
            ->limit(20)
            ->get();

        // Status tiap perangkat ("sensor|ip"): dikarantina mengalahkan insiden aktif.
        $statusPerangkat = DB::table('incidents')
            ->whereIn('status', ['baru', 'dikarantina'])
            ->get(['sensor', 'client_ip', 'status'])
            ->groupBy(fn ($i) => $i->sensor.'|'.$i->client_ip)
            ->map(fn ($grup) => $grup->contains('status', 'dikarantina') ? 'dikarantina' : 'insiden');

        $audit = DB::table('audit_logs')
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        $aktivitas = $this->aktivitasPerMenit($sekarang);

        return view('benteng.dashboard', compact(
            'sekarang', 'sensor', 'metrik', 'incidents', 'perangkat', 'statusPerangkat', 'audit', 'aktivitas'
        ))->fragmentIf($request->hasHeader('X-Benteng-Sebagian'), 'isi');
    }

    /**
     * Analis menekan "Karantina": buat perintah untuk sensor dan tandai semua
     * insiden aktif perangkat itu, karena karantina berlaku per perangkat.
     */
    public function karantina(int $id): RedirectResponse
    {
        $insiden = DB::table('incidents')->find($id);
        if (! $insiden || $insiden->status !== 'baru') {
            return back()->with('galat', 'Insiden tidak ditemukan atau sudah ditangani.');
        }

        // IP berasal dari data insiden yang IP-nya sudah divalidasi saat ingest.
        if (! filter_var($insiden->client_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return back()->with('galat', "IP {$insiden->client_ip} tidak bisa dikarantina (hanya IPv4).");
        }

        DB::transaction(function () use ($insiden) {
            $this->kirimPerintah($insiden, 'quarantine');

            DB::table('incidents')
                ->where('sensor', $insiden->sensor)
                ->where('client_ip', $insiden->client_ip)
                ->where('status', 'baru')
                ->update(['status' => 'dikarantina', 'updated_at' => now()]);

            $this->catatAudit($insiden, 'minta karantina');
        });

        return back()->with('pesan', "Perintah karantina {$insiden->client_ip} dikirim ke {$insiden->sensor}. Menunggu sensor menerapkan.");
    }

    /**
     * Analis menandai insiden sebagai false positive.
     */
    public function falsePositive(int $id): RedirectResponse
    {
        $insiden = DB::table('incidents')->find($id);
        if (! $insiden || $insiden->status !== 'baru') {
            return back()->with('galat', 'Insiden tidak ditemukan atau sudah ditangani.');
        }

        DB::transaction(function () use ($insiden) {
            DB::table('incidents')->where('id', $insiden->id)->update([
                'status' => 'false_positive',
                'updated_at' => now(),
            ]);

            $this->catatAudit($insiden, 'tandai false positive');
        });

        return back()->with('pesan', "Insiden #{$insiden->id} ditandai false positive.");
    }

    /**
     * Analis melepas karantina. Semua insiden perangkat yang dikarantina
     * kembali aktif agar tetap ditindaklanjuti.
     */
    public function lepas(int $id): RedirectResponse
    {
        $insiden = DB::table('incidents')->find($id);
        if (! $insiden || $insiden->status !== 'dikarantina') {
            return back()->with('galat', 'Insiden tidak ditemukan atau tidak sedang dikarantina.');
        }

        if (! filter_var($insiden->client_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return back()->with('galat', "IP {$insiden->client_ip} tidak valid.");
        }

        DB::transaction(function () use ($insiden) {
            $this->kirimPerintah($insiden, 'unquarantine');

            DB::table('incidents')
                ->where('sensor', $insiden->sensor)
                ->where('client_ip', $insiden->client_ip)
                ->where('status', 'dikarantina')
                ->update(['status' => 'baru', 'updated_at' => now()]);

            $this->catatAudit($insiden, 'lepas karantina');
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

    private function catatAudit(object $insiden, string $aksi): void
    {
        DB::table('audit_logs')->insert([
            'pelaku' => 'analis',
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
