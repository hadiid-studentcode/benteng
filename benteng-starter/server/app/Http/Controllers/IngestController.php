<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IngestController extends Controller
{
    /**
     * Menerima batch query DNS dari sensor BENTENG.
     */
    public function dns(Request $request)
    {
        $token = (string) config('benteng.sensor_token');
        abort_if($token === '', 500, 'BENTENG_SENSOR_TOKEN belum diisi di .env');
        abort_unless(hash_equals($token, (string) $request->header('X-Sensor-Token')), 401, 'Token sensor salah');

        $data = $request->validate([
            'sensor' => ['required', 'string', 'max:32'],
            'events' => ['required', 'array', 'max:5000'],
            'events.*.ts' => ['required', 'string'],
            'events.*.type' => ['required', 'string', 'max:10'],
            'events.*.domain' => ['required', 'string', 'max:255'],
            'events.*.client' => ['required', 'ip'],
        ]);

        $rows = [];
        foreach ($data['events'] as $event) {
            try {
                // Format dari logread OpenWrt, contoh: "Oct 24 17:16:17 2026"
                $at = Carbon::createFromFormat('M j H:i:s Y', $event['ts'], 'Asia/Jakarta');
            } catch (\Throwable $e) {
                continue;
            }

            $rows[] = [
                'sensor' => $data['sensor'],
                'queried_at' => $at,
                'qtype' => $event['type'],
                'domain' => strtolower(rtrim($event['domain'], '.')),
                'client_ip' => $event['client'],
                'created_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('dns_events')->insert($chunk);
        }

        return response()->json(['diterima' => count($rows)]);
    }
}
