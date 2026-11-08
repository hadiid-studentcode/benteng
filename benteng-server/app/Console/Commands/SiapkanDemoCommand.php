<?php

namespace App\Console\Commands;

use App\Http\Controllers\CommandController;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Menyiapkan BENTENG untuk dicoba juri tanpa sensor dan tanpa VM: akun demo, token
 * sensor, dan data simulasi 25 menit terakhir (beaconing, domain acak, dan perangkat
 * asing). Sensor ditiru oleh benteng:sensor-tiruan.
 *
 *   php artisan benteng:siapkan-demo
 *
 * Aman untuk lab sungguhan: perintah ini menolak berjalan bila database sudah berisi
 * data dan mode demo belum aktif.
 */
class SiapkanDemoCommand extends Command
{
    /**
     * Akun demo: email => [nama, peran, kata sandi]. Hanya untuk instalasi lokal juri.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    public const AKUN = [
        'analis@demo.local' => ['Analis Demo', 'analis', 'analis-demo'],
        'pengamat@demo.local' => ['Pengamat Demo', 'pengamat', 'pengamat-demo'],
    ];

    protected $signature = 'benteng:siapkan-demo';

    protected $description = 'Siapkan mode demo: akun demo dan data simulasi, tanpa sensor sungguhan';

    public function handle(): int
    {
        if (! config('benteng.mode_demo')) {
            if (DB::table('dns_events')->exists() || DB::table('incidents')->exists()) {
                $this->error('Database ini sudah berisi data lab. Mode demo tidak diaktifkan agar data itu tidak terhapus.');
                $this->line('Bila memang ingin menimpanya, isi BENTENG_MODE_DEMO=true di .env lalu jalankan lagi.');

                return self::FAILURE;
            }

            $this->tulisEnv('BENTENG_MODE_DEMO', 'true');
            config(['benteng.mode_demo' => true]);
        }

        if (config('benteng.sensor_token') === '') {
            $token = bin2hex(random_bytes(16));
            $this->tulisEnv('BENTENG_SENSOR_TOKEN', $token);
            config(['benteng.sensor_token' => $token]);
        }

        foreach (self::AKUN as $email => [$nama, $peran, $sandi]) {
            User::updateOrCreate(['email' => $email], ['name' => $nama, 'peran' => $peran, 'password' => $sandi]);
        }

        $this->callSilently('benteng:reset-demo', ['--force' => true]);
        DB::table('commands')->delete();
        $this->isiData();

        $this->info('Mode demo siap. Data simulasi dibuat ulang untuk 25 menit terakhir.');
        $this->table(['Email', 'Kata sandi', 'Peran'], collect(self::AKUN)
            ->map(fn ($akun, $email) => [$email, $akun[2], User::PERAN[$akun[1]]])
            ->values()
            ->all());
        $this->line('Jalankan `php artisan serve`, lalu buka http://localhost:8000');

        return self::SUCCESS;
    }

    /**
     * Data DNS dan DHCP simulasi, lalu detektor menemukan insidennya seperti pada lab.
     */
    private function isiData(): void
    {
        $sensor = SensorTiruanCommand::SENSOR;
        $sekarang = now()->getTimestamp();
        $baris = [];
        $query = function (string $ip, string $domain, int $waktu) use (&$baris, $sensor) {
            $baris[] = [
                'sensor' => $sensor,
                'queried_at' => date('Y-m-d H:i:s', $waktu),
                'qtype' => 'A',
                'domain' => $domain,
                'client_ip' => $ip,
                'line_hash' => sha1(count($baris).$ip.$domain.$waktu),
                'created_at' => date('Y-m-d H:i:s', $waktu + 30),
            ];
        };

        // PC staf terinfeksi: beacon tiap ~30 detik ke server pengendali, dan nama domain acak.
        for ($t = $sekarang - 1500; $t < $sekarang - 60; $t += 30 + random_int(-1, 1)) {
            $query('192.168.1.142', 'c2-sim.example', $t);
        }
        for ($i = 0; $i < 40; $i++) {
            $query('192.168.1.142', substr(str_shuffle('bcdfghjklmnpqrstvwxz'), 0, 14).'.example', $sekarang - 1400 + $i * 9);
        }

        // PC staf lain yang dipakai normal.
        foreach (['www.detik.com', 'www.kompas.com', 'web.whatsapp.com', 'www.youtube.com', 'mail.google.com'] as $domain) {
            for ($k = 0; $k < 10; $k++) {
                $query('192.168.1.203', $domain, $sekarang - random_int(60, 1500));
            }
        }

        foreach (array_chunk($baris, 200) as $potongan) {
            DB::table('dns_events')->insertOrIgnore($potongan);
        }

        $perangkat = fn (string $mac, string $ip, string $host, bool $sah, int $sejak) => [
            'sensor' => $sensor, 'mac' => $mac, 'ip' => $ip, 'hostname' => $host, 'dikenal' => $sah,
            'pertama_terlihat' => date('Y-m-d H:i:s', $sekarang - $sejak), 'terakhir_terlihat' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('perangkat')->insert([
            $perangkat('08:00:27:23:23:0f', '192.168.1.142', 'pc-staf-01', true, 3000),
            $perangkat('08:00:27:41:7a:10', '192.168.1.203', 'pc-staf-02', true, 3000),
            $perangkat('0a:3c:91:7d:e2:4f', '192.168.1.214', 'laptop-tamu', false, 300),
        ]);

        $this->callSilently('benteng:deteksi');

        // Data dibuat sekaligus, jadi waktu muncul insiden disesuaikan dengan yang terukur di lab:
        // domain acak 1-3 menit, beaconing 5-6 menit, perangkat asing sekitar 1 menit.
        $jeda = ['dga' => 120, 'beaconing' => 330, 'perangkat_asing' => 60];
        foreach (DB::table('incidents')->get() as $insiden) {
            if ($insiden->mulai_pada && isset($jeda[$insiden->jenis])) {
                $muncul = min(strtotime($insiden->mulai_pada) + $jeda[$insiden->jenis], $sekarang);
                DB::table('incidents')->where('id', $insiden->id)->update([
                    'created_at' => date('Y-m-d H:i:s', $muncul),
                    'updated_at' => date('Y-m-d H:i:s', $muncul),
                ]);
            }
        }

        Cache::forever(CommandController::kunciDetak($sensor), now()->toDateTimeString());
        Cache::forget('benteng:demo:deteksi');
    }

    /**
     * Isi atau tambahkan satu nilai di berkas .env.
     */
    private function tulisEnv(string $kunci, string $nilai): void
    {
        $path = base_path('.env');
        $isi = file_exists($path) ? file_get_contents($path) : '';
        $baris = "$kunci=$nilai";

        $isi = preg_match("/^$kunci=.*$/m", $isi)
            ? preg_replace("/^$kunci=.*$/m", $baris, $isi)
            : rtrim($isi, "\r\n")."\n\n$baris\n";

        file_put_contents($path, $isi);
    }
}
