<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\MembacaDataPantauan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Daftar dan detail insiden, serta tindakan analis terhadap insiden.
 */
class InsidenController extends Controller
{
    use MembacaDataPantauan;

    /**
     * Filter di halaman daftar beserta status insiden yang dicakup.
     */
    private const FILTER = [
        'semua' => null,
        'aktif' => 'baru',
        'dikarantina' => 'dikarantina',
        'ditutup' => 'false_positive',
    ];

    public function index(Request $request): View|string
    {
        $filter = array_key_exists($request->query('status'), self::FILTER) ? $request->query('status') : 'semua';

        $perStatus = DB::table('incidents')
            ->selectRaw('status, COUNT(*) as jumlah')
            ->groupBy('status')
            ->get()
            ->pluck('jumlah', 'status');

        $query = $this->insidenUrut();
        if (self::FILTER[$filter]) {
            $query->where('status', self::FILTER[$filter]);
        }

        return $this->tampilkan($request, 'benteng.insiden.index', [
            'filter' => $filter,
            'jumlah' => [
                'semua' => (int) $perStatus->sum(),
                'aktif' => (int) ($perStatus['baru'] ?? 0),
                'dikarantina' => (int) ($perStatus['dikarantina'] ?? 0),
                'ditutup' => (int) ($perStatus['false_positive'] ?? 0),
            ],
            'incidents' => $this->lengkapiInsiden($query->get(), $this->daftarPerangkat()),
            'bisaBertindak' => $request->user()->can('bertindak'),
        ]);
    }

    public function show(Request $request, int $id): View|string
    {
        $insiden = DB::table('incidents')->find($id);
        abort_unless($insiden, 404);

        $this->lengkapiInsiden(collect([$insiden]), $this->daftarPerangkat());

        // Sidik beacon berbentuk "beacon|sensor|client|domain".
        $domain = $insiden->jenis === 'beaconing' ? (explode('|', $insiden->sidik, 4)[3] ?? null) : null;
        $sekarang = Carbon::now();

        return $this->tampilkan($request, 'benteng.insiden.show', [
            'insiden' => $insiden,
            'domain' => $domain,
            'ketukan' => $domain ? $this->ketukanBeacon($insiden, $domain) : [],
            'playbook' => config('benteng.playbook.'.$insiden->jenis),
            // Kode teknik mengandung titik (T1071.004), jadi jangan pakai notasi titik config().
            'namaTeknik' => config('benteng.attck')[$insiden->attck] ?? null,
            'linimasa' => $this->linimasa($insiden),
            'sekarang' => $sekarang,
            'bisaBertindak' => $request->user()->can('bertindak'),
        ]);
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

        return back()->with('pesan', "Perintah karantina {$insiden->client_ip} dikirim ke sensor {$insiden->sensor}.");
    }

    /**
     * Analis menandai insiden bukan ancaman (false positive). Untuk insiden
     * perangkat asing, ini berarti perangkat didaftarkan sebagai perangkat sah satuan.
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
            : "Insiden #{$insiden->id} ditandai bukan ancaman.");
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

        return back()->with('pesan', "Perintah lepas karantina {$insiden->client_ip} dikirim ke sensor {$insiden->sensor}.");
    }

    /**
     * Riwayat penanganan perangkat sejak insiden terdeteksi, dari jejak audit.
     *
     * @return array<int, array{waktu: ?string, judul: string, ket: string, warna: string, menunggu?: bool}>
     */
    private function linimasa(object $insiden): array
    {
        $item = [];
        if ($insiden->mulai_pada) {
            $item[] = ['waktu' => $insiden->mulai_pada, 'judul' => 'Aktivitas mencurigakan pertama', 'ket' => "Tercatat oleh sensor {$insiden->sensor}", 'warna' => 'abu'];
        }
        $item[] = ['waktu' => $insiden->created_at, 'judul' => 'Insiden terdeteksi', 'ket' => 'Dibuat otomatis oleh detektor', 'warna' => 'merah'];

        $kalimat = [
            'minta karantina' => ['Karantina diminta', 'biru'],
            'quarantine ok' => ['Karantina diterapkan', 'biru'],
            'quarantine gagal' => ['Karantina gagal diterapkan', 'merah'],
            'lepas karantina' => ['Lepas karantina diminta', 'hijau'],
            'unquarantine ok' => ['Karantina dilepas', 'hijau'],
            'unquarantine gagal' => ['Karantina gagal dilepas', 'merah'],
            'tandai false positive' => ['Ditandai bukan ancaman', 'abu'],
            'daftarkan perangkat' => ['Didaftarkan sebagai perangkat sah', 'hijau'],
        ];

        $audit = DB::table('audit_logs')
            ->where('target', $insiden->client_ip)
            ->where('created_at', '>=', $insiden->created_at)
            ->orderBy('id')
            ->get();

        foreach ($audit as $a) {
            $insidenLain = $a->incident_id && (int) $a->incident_id !== (int) $insiden->id;
            // Penutupan insiden lain tidak mengubah keadaan perangkat, jadi tidak ditampilkan.
            if ($insidenLain && in_array($a->aksi, ['tandai false positive', 'daftarkan perangkat'], true)) {
                continue;
            }
            [$judul, $warna] = $kalimat[$a->aksi] ?? [$a->aksi, 'abu'];
            $pelaku = str_starts_with($a->pelaku, 'sensor:') ? 'sensor '.substr($a->pelaku, 7) : $a->pelaku;
            $item[] = [
                'waktu' => $a->created_at,
                'judul' => $judul,
                'ket' => "Oleh $pelaku".($insidenLain ? " (dari insiden #{$a->incident_id})" : ''),
                'warna' => $warna,
            ];
        }

        if ($insiden->perintah?->status === 'pending' && $insiden->status !== 'false_positive') {
            $item[] = ['waktu' => null, 'judul' => 'Menunggu sensor', 'ket' => "Perintah belum dikonfirmasi sensor {$insiden->sensor}", 'warna' => 'kuning', 'menunggu' => true];
        }

        return $item;
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
}
