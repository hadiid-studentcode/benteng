<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
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

    private const MAX_LINES = 20000;

    /**
     * Menerima baris log query DNS mentah dari sensor BENTENG.
     */
    public function dns(Request $request)
    {
        $token = (string) config('benteng.sensor_token');
        abort_if($token === '', 500, 'BENTENG_SENSOR_TOKEN belum diisi di .env');
        abort_unless(hash_equals($token, (string) $request->header('X-Sensor-Token')), 401, 'Token sensor salah');

        $sensor = (string) $request->header('X-Sensor-Id');
        if (! preg_match('/^[A-Za-z0-9_-]{1,32}$/', $sensor)) {
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
}