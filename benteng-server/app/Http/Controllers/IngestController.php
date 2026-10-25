<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IngestController extends Controller
{
    /**
     * Pola satu baris query dnsmasq dari logread OpenWrt, contoh:
     * Sat Oct 24 17:16:17 2026 daemon.info dnsmasq[1]: 1 192.168.1.142/60011 query[A] example.com from 192.168.1.142
     */
    private const LINE = '/^\w{3} (\w{3}) +(\d{1,2}) (\d{2}:\d{2}:\d{2}) (\d{4}) .*?query\[([^\]]{1,16})\] (\S{1,255}) from (\S+)/';

    /**
     * Pola satu baris /tmp/dhcp.leases dnsmasq, contoh:
     * 1761400000 08:00:27:4a:1b:2c 192.168.1.142 korban 01:08:00:27:4a:1b:2c
     */
    private const LEASE = '/^\d+ ([0-9A-Fa-f]{2}(?::[0-9A-Fa-f]{2}){5}) (\S+) (\S+)/';

    private const MAX_LINES = 20000;

    /**
     * Menerima baris log query DNS mentah dari sensor BENTENG.
     */
    public function dns(Request $request): JsonResponse
    {
        $sensor = $this->cekSensor($request);
        if ($sensor === null) {
            return response()->json(['error' => 'Header X-Sensor-Id kosong atau tidak valid'], 422);
        }

        $lines = preg_split('/\r?\n/', $request->getContent(), -1, PREG_SPLIT_NO_EMPTY);
        $lines = array_slice($lines, 0, self::MAX_LINES);

        $rows = [];
        $skipped = [];

        foreach ($lines as $line) {
            if (! preg_match(self::LINE, $line, $m)) {
                $skipped[] = $line;

                continue;
            }

            [, $month, $day, $time, $year, $type, $domain, $client] = $m;

            if (filter_var($client, FILTER_VALIDATE_IP) === false) {
                $skipped[] = $line;

                continue;
            }

            try {
                $at = Carbon::createFromFormat('M j H:i:s Y', "$month $day $time $year", 'Asia/Jakarta');
            } catch (\Throwable $e) {
                $skipped[] = $line;

                continue;
            }

            $rows[] = [
                'sensor' => $sensor,
                'queried_at' => $at,
                'qtype' => $type,
                'domain' => strtolower(rtrim($domain, '.')),
                'client_ip' => $client,
                'line_hash' => sha1($sensor.'|'.$line),
                'created_at' => now(),
            ];
        }

        // Baris yang sudah pernah disimpan (kiriman ulang) diabaikan lewat indeks unik line_hash.
        $saved = 0;
        foreach (array_chunk($rows, 500) as $chunk) {
            $saved += DB::table('dns_events')->insertOrIgnore($chunk);
        }

        if ($skipped) {
            Log::info('BENTENG ingest: sebagian baris dilewati', [
                'sensor' => $sensor,
                'jumlah' => count($skipped),
                'contoh' => array_slice($skipped, 0, 3),
            ]);
        }

        return response()->json([
            'diterima' => count($rows),
            'baru' => $saved,
            'dilewati' => count($skipped),
        ]);
    }

    /**
     * Menerima isi /tmp/dhcp.leases dari sensor, untuk daftar perangkat dan deteksi perangkat asing.
     * Laporan pertama sebuah sensor dijadikan baseline: perangkat di dalamnya dianggap sah.
     */
    public function dhcp(Request $request): JsonResponse
    {
        $sensor = $this->cekSensor($request);
        if ($sensor === null) {
            return response()->json(['error' => 'Header X-Sensor-Id kosong atau tidak valid'], 422);
        }

        $lines = preg_split('/\r?\n/', $request->getContent(), -1, PREG_SPLIT_NO_EMPTY);
        $lines = array_slice($lines, 0, 1000);

        $baseline = ! DB::table('perangkat')->where('sensor', $sensor)->exists();
        $diterima = 0;
        $baru = 0;

        DB::transaction(function () use ($lines, $sensor, $baseline, &$diterima, &$baru) {
            foreach ($lines as $line) {
                if (! preg_match(self::LEASE, $line, $m) || ! filter_var($m[2], FILTER_VALIDATE_IP)) {
                    continue;
                }
                $mac = strtolower($m[1]);
                $hostname = $m[3] === '*' ? null : substr(preg_replace('/[^A-Za-z0-9._-]/', '', $m[3]), 0, 64);
                $diterima++;

                $ada = DB::table('perangkat')->where('sensor', $sensor)->where('mac', $mac)->exists();
                if ($ada) {
                    DB::table('perangkat')->where('sensor', $sensor)->where('mac', $mac)->update([
                        'ip' => $m[2], 'hostname' => $hostname, 'terakhir_terlihat' => now(), 'updated_at' => now(),
                    ]);

                    continue;
                }

                DB::table('perangkat')->insert([
                    'sensor' => $sensor, 'mac' => $mac, 'ip' => $m[2], 'hostname' => $hostname,
                    'dikenal' => $baseline,
                    'pertama_terlihat' => now(), 'terakhir_terlihat' => now(),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $baru++;
            }
        });

        return response()->json(['diterima' => $diterima, 'baru' => $baru, 'baseline' => $baseline]);
    }

    /**
     * Validasi token sensor lalu kembalikan ID sensor dari header, atau null bila tidak valid.
     */
    private function cekSensor(Request $request): ?string
    {
        $token = (string) config('benteng.sensor_token');
        abort_if($token === '', 500, 'BENTENG_SENSOR_TOKEN belum diisi di .env');
        abort_unless(hash_equals($token, (string) $request->header('X-Sensor-Token')), 401, 'Token sensor salah');

        $sensor = (string) $request->header('X-Sensor-Id');

        return preg_match('/^[A-Za-z0-9_-]{1,32}$/', $sensor) ? $sensor : null;
    }
}
