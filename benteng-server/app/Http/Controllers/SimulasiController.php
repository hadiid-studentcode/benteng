<?php

namespace App\Http\Controllers;

use App\Console\Commands\SensorTiruanCommand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mode demo: juri memicu aktivitas mencurigakan dari dashboard, lalu melihat BENTENG
 * mendeteksinya. Data dibuat di server, tidak ada perangkat atau sensor sungguhan yang
 * tersentuh. Aktivitas 6 menit dibuat sekaligus agar juri tidak perlu menunggu.
 */
class SimulasiController extends Controller
{
    /**
     * Rentang IP perangkat hasil simulasi. Data demo diperbarui otomatis tiap jam.
     */
    private const IP_AWAL = 150;

    private const IP_AKHIR = 199;

    private const DOMAIN_C2 = ['update-cek.example', 'sinkron-data.example', 'cdn-pembaruan.example', 'layanan-akun.example'];

    public function store(Request $request): RedirectResponse
    {
        abort_unless(config('benteng.mode_demo'), 404);

        $jenis = $request->validate(['jenis' => 'required|in:terinfeksi,asing'])['jenis'];
        $sensor = SensorTiruanCommand::SENSOR;

        $ip = $this->ipBebas($sensor);
        if (! $ip) {
            return back()->with('galat', 'Batas simulasi tercapai. Data demo diperbarui otomatis setiap jam.');
        }

        $nomor = (int) Str::afterLast($ip, '.');

        if ($jenis === 'terinfeksi') {
            $domain = self::DOMAIN_C2[array_rand(self::DOMAIN_C2)];
            $this->catatPerangkat($sensor, $ip, "pc-staf-$nomor", true);
            $this->buatQueryTerinfeksi($sensor, $ip, $domain);
            $pesan = "PC $ip mulai melapor ke $domain dan menanyakan domain acak. Insiden muncul dalam beberapa detik.";
        } else {
            $this->catatPerangkat($sensor, $ip, "hp-tamu-$nomor", false);
            $pesan = "Perangkat tak dikenal $ip bergabung ke jaringan. Insiden muncul dalam beberapa detik.";
        }

        // Detektor demo dijalankan pada pemuatan halaman berikutnya, tanpa menunggu jeda satu menit.
        Cache::forget('benteng:demo:deteksi');

        return back()->with('pesan', $pesan);
    }

    private function ipBebas(string $sensor): ?string
    {
        $terpakai = DB::table('perangkat')->where('sensor', $sensor)->pluck('ip')->flip();

        for ($n = self::IP_AWAL; $n <= self::IP_AKHIR; $n++) {
            if (! $terpakai->has("192.168.1.$n")) {
                return "192.168.1.$n";
            }
        }

        return null;
    }

    private function catatPerangkat(string $sensor, string $ip, string $hostname, bool $dikenal): void
    {
        // Perangkat sah sudah terlihat sejak awal; perangkat asing baru saja bergabung.
        $sejak = $dikenal ? now()->subMinutes(30) : now();

        DB::table('perangkat')->insert([
            'sensor' => $sensor,
            'mac' => '0a:'.implode(':', array_map(fn () => sprintf('%02x', random_int(0, 255)), range(1, 5))),
            'ip' => $ip,
            'hostname' => $hostname,
            'dikenal' => $dikenal,
            'pertama_terlihat' => $sejak,
            'terakhir_terlihat' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Beacon tiap ~30 detik selama 6 menit terakhir ke satu domain, ditambah 12 nama domain acak.
     */
    private function buatQueryTerinfeksi(string $sensor, string $ip, string $domain): void
    {
        $sekarang = now()->getTimestamp();
        $baris = [];
        $query = function (string $nama, int $waktu) use (&$baris, $sensor, $ip) {
            $baris[] = [
                'sensor' => $sensor,
                'queried_at' => date('Y-m-d H:i:s', $waktu),
                'qtype' => 'A',
                'domain' => $nama,
                'client_ip' => $ip,
                'line_hash' => sha1("simulasi|$ip|$nama|$waktu|".count($baris)),
                'created_at' => now(),
            ];
        };

        for ($t = $sekarang - 360; $t <= $sekarang; $t += 30 + random_int(-1, 1)) {
            $query($domain, $t);
        }
        for ($i = 0; $i < 12; $i++) {
            $query(substr(str_shuffle('bcdfghjklmnpqrstvwxz'), 0, 14).'.example', $sekarang - 300 + $i * 20);
        }

        DB::table('dns_events')->insertOrIgnore($baris);
    }
}
