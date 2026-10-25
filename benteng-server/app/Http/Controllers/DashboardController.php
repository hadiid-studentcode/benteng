<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $batas24 = Carbon::now()->subDay();

        $metrik = [
            'sensor' => DB::table('dns_events')->distinct()->count('sensor'),
            'perangkat' => DB::table('dns_events')->distinct()->count('client_ip'),
            'query24' => DB::table('dns_events')->where('queried_at', '>=', $batas24)->count(),
            'insidenAktif' => DB::table('incidents')->where('status', 'baru')->count(),
        ];

        $incidents = DB::table('incidents')
            ->orderByDesc('skor')
            ->orderByDesc('id')
            ->get();

        $perangkat = DB::table('dns_events')
            ->select('client_ip', 'sensor', DB::raw('COUNT(*) as jumlah'),
                DB::raw('MIN(queried_at) as awal'), DB::raw('MAX(queried_at) as akhir'))
            ->groupBy('client_ip', 'sensor')
            ->orderByDesc('jumlah')
            ->limit(20)
            ->get();

        // Perangkat yang punya insiden aktif, untuk menandai barisnya merah.
        $ipBermasalah = DB::table('incidents')
            ->where('status', 'baru')
            ->pluck('client_ip')
            ->all();

        return view('benteng.dashboard', compact('metrik', 'incidents', 'perangkat', 'ipBermasalah'));
    }

    /**
     * Analis menekan "Karantina": buat perintah untuk sensor dan ubah status insiden.
     */
    public function karantina(Request $request, int $id)
    {
        $insiden = DB::table('incidents')->where('id', $id)->first();
        if (! $insiden) {
            return back();
        }

        // IP berasal dari data insiden yang IP-nya sudah divalidasi saat ingest.
        if (filter_var($insiden->client_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            DB::table('commands')->insert([
                'sensor' => $insiden->sensor,
                'aksi' => 'quarantine',
                'target_ip' => $insiden->client_ip,
                'incident_id' => $insiden->id,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('incidents')->where('id', $id)->update([
                'status' => 'dikarantina',
                'updated_at' => now(),
            ]);

            DB::table('audit_logs')->insert([
                'pelaku' => 'analis',
                'aksi' => 'minta karantina',
                'target' => $insiden->client_ip,
                'incident_id' => $insiden->id,
                'created_at' => now(),
            ]);
        }

        return back();
    }

    /**
     * Analis menandai insiden sebagai false positive.
     */
    public function falsePositive(Request $request, int $id)
    {
        $insiden = DB::table('incidents')->where('id', $id)->first();
        if ($insiden) {
            DB::table('incidents')->where('id', $id)->update([
                'status' => 'false_positive',
                'updated_at' => now(),
            ]);

            DB::table('audit_logs')->insert([
                'pelaku' => 'analis',
                'aksi' => 'tandai false positive',
                'target' => $insiden->client_ip,
                'incident_id' => $insiden->id,
                'created_at' => now(),
            ]);
        }

        return back();
    }

    /**
     * Analis melepas karantina.
     */
    public function lepas(Request $request, int $id)
    {
        $insiden = DB::table('incidents')->where('id', $id)->first();
        if ($insiden && filter_var($insiden->client_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            DB::table('commands')->insert([
                'sensor' => $insiden->sensor,
                'aksi' => 'unquarantine',
                'target_ip' => $insiden->client_ip,
                'incident_id' => $insiden->id,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('incidents')->where('id', $id)->update([
                'status' => 'baru',
                'updated_at' => now(),
            ]);

            DB::table('audit_logs')->insert([
                'pelaku' => 'analis',
                'aksi' => 'lepas karantina',
                'target' => $insiden->client_ip,
                'incident_id' => $insiden->id,
                'created_at' => now(),
            ]);
        }

        return back();
    }
}