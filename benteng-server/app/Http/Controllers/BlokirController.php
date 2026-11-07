<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\MembacaDataPantauan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Blokir domain di DNS sensor, agar semua perangkat di jaringan ikut terlindungi.
 */
class BlokirController extends Controller
{
    use MembacaDataPantauan;

    private const POLA_DOMAIN = '/^(?!-)[a-z0-9-]{1,63}(?<!-)(\.(?!-)[a-z0-9-]{1,63}(?<!-))+$/';

    public function index(Request $request): View|string
    {
        $blokir = DB::table('domain_diblokir')->orderByDesc('id')->get();

        foreach ($blokir as $b) {
            $percobaan = $this->queryKeDomain($b->sensor, $b->domain, $b->created_at);
            $b->jumlah_percobaan = (clone $percobaan)->count();
            $b->jumlah_perangkat = (clone $percobaan)->distinct()->count('client_ip');
        }

        return $this->tampilkan($request, 'benteng.blokir', [
            'blokir' => $blokir,
            'sensor' => $this->statusSensor(Carbon::now()),
            'bisaBertindak' => $request->user()->can('bertindak'),
        ]);
    }

    /**
     * Analis menambah domain secara manual, mis. dari peringatan satuan atas.
     */
    public function store(Request $request): RedirectResponse
    {
        $domain = strtolower(trim((string) $request->input('domain')));
        $sensor = (string) $request->input('sensor');

        if (! preg_match(self::POLA_DOMAIN, $domain) || strlen($domain) > 253) {
            return back()->with('galat', 'Format domain tidak valid. Contoh yang benar: server-c2.example');
        }
        if (! $this->statusSensor(Carbon::now())->contains('nama', $sensor)) {
            return back()->with('galat', 'Sensor tidak ditemukan.');
        }

        if (! $this->blokir($request, $sensor, $domain)) {
            return back()->with('galat', "Domain $domain sudah diblokir.");
        }

        return back()->with('pesan', "Domain $domain dikirim ke sensor $sensor untuk diblokir.");
    }

    /**
     * Analis memblokir domain tujuan beacon dari halaman insiden. Insiden aktif dianggap
     * tertangani tanpa memutus perangkat, cocok untuk perangkat yang harus tetap bekerja.
     */
    public function dariInsiden(Request $request, int $id): RedirectResponse
    {
        $insiden = DB::table('incidents')->find($id);
        $domain = $insiden ? $this->domainBeacon($insiden) : null;

        if (! $domain || ! in_array($insiden->status, ['baru', 'dikarantina'], true)) {
            return back()->with('galat', 'Domain insiden ini tidak dapat diblokir.');
        }

        DB::transaction(function () use ($request, $insiden, $domain) {
            $this->blokir($request, $insiden->sensor, $domain, $insiden->id);

            DB::table('incidents')
                ->where('id', $insiden->id)
                ->where('status', 'baru')
                ->update(['status' => 'diblokir', 'updated_at' => now()]);
        });

        return back()->with('pesan', "Domain $domain dikirim ke sensor {$insiden->sensor} untuk diblokir.");
    }

    /**
     * Lepas blokir. Insiden asal kembali aktif karena perangkatnya bisa menghubungi domain itu lagi.
     */
    public function lepas(Request $request, int $id): RedirectResponse
    {
        $blokir = DB::table('domain_diblokir')->find($id);
        if (! $blokir) {
            return back()->with('galat', 'Blokir tidak ditemukan.');
        }

        DB::transaction(function () use ($request, $blokir) {
            DB::table('domain_diblokir')->where('id', $blokir->id)->delete();

            if ($blokir->incident_id) {
                DB::table('incidents')
                    ->where('id', $blokir->incident_id)
                    ->where('status', 'diblokir')
                    ->update(['status' => 'baru', 'updated_at' => now()]);
            }

            $this->catatAudit($request, 'lepas blokir domain', $blokir->domain, $blokir->incident_id);
        });

        return back()->with('pesan', "Blokir domain {$blokir->domain} dilepas.");
    }

    private function blokir(Request $request, string $sensor, string $domain, ?int $insidenId = null): bool
    {
        $baru = DB::table('domain_diblokir')->insertOrIgnore([
            'sensor' => $sensor,
            'domain' => $domain,
            'incident_id' => $insidenId,
            'pelaku' => mb_substr($request->user()->name, 0, 64),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($baru) {
            $this->catatAudit($request, 'blokir domain', $domain, $insidenId);
        }

        return (bool) $baru;
    }

    private function catatAudit(Request $request, string $aksi, string $domain, ?int $insidenId): void
    {
        DB::table('audit_logs')->insert([
            'pelaku' => mb_substr($request->user()->name, 0, 64),
            'user_id' => $request->user()->id,
            'aksi' => $aksi,
            'target' => mb_substr($domain, 0, 64),
            'incident_id' => $insidenId,
            'created_at' => now(),
        ]);
    }
}
