<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Endpoint yang dipakai sensor untuk menarik dan menyelesaikan perintah.
 * Semua diautentikasi dengan token sensor (header X-Sensor-Token).
 */
class CommandController extends Controller
{
    /**
     * Kunci cache waktu terakhir sensor menarik perintah.
     */
    public static function kunciDetak(string $sensor): string
    {
        return "benteng:detak:$sensor";
    }

    private function cekToken(Request $request): void
    {
        $token = (string) config('benteng.sensor_token');
        abort_if($token === '', 500, 'BENTENG_SENSOR_TOKEN belum diisi di .env');
        abort_unless(hash_equals($token, (string) $request->header('X-Sensor-Token')), 401, 'Token sensor salah');
    }

    /**
     * Sensor menarik perintah yang menunggu. Balasan berupa teks baris:
     *   <id> <aksi> <ip>
     * Format baris sengaja dipakai agar mudah diproses skrip shell di sensor.
     */
    public function ambil(Request $request, string $sensor)
    {
        $this->cekToken($request);

        // Responder menarik tiap 5 detik; dashboard memakai ini sebagai tanda sensor online.
        Cache::forever(self::kunciDetak($sensor), now()->toDateTimeString());

        $commands = DB::table('commands')
            ->where('sensor', $sensor)
            ->where('status', 'pending')
            ->orderBy('id')
            ->get(['id', 'aksi', 'target_ip']);

        // Tandai sudah diambil (tetap pending sampai sensor meng-ack).
        DB::table('commands')
            ->where('sensor', $sensor)
            ->where('status', 'pending')
            ->whereNull('diambil_pada')
            ->update(['diambil_pada' => now()]);

        $baris = $commands->map(fn ($c) => "{$c->id} {$c->aksi} {$c->target_ip}")->implode("\n");

        return response($baris."\n", 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Daftar IP yang seharusnya sedang dikarantina oleh sensor ini, satu per baris.
     * Responder memakainya saat mulai dan berkala, karena set nftables hilang saat sensor reboot.
     */
    public function karantina(Request $request, string $sensor)
    {
        $this->cekToken($request);

        $ips = DB::table('incidents')
            ->where('sensor', $sensor)
            ->where('status', 'dikarantina')
            ->distinct()
            ->pluck('client_ip')
            ->filter(fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
            ->implode("\n");

        return response($ips."\n", 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Sensor melaporkan hasil sebuah perintah (hasil=ok|gagal).
     */
    public function ack(Request $request, string $sensor, int $id)
    {
        $this->cekToken($request);

        $hasil = $request->input('hasil') === 'ok' ? 'ok' : 'gagal';

        $cmd = DB::table('commands')->where('id', $id)->where('sensor', $sensor)->first();
        abort_if(! $cmd, 404, 'Perintah tidak ditemukan');

        DB::table('commands')->where('id', $id)->update([
            'status' => $hasil === 'ok' ? 'selesai' : 'gagal',
            'hasil' => $hasil,
            'selesai_pada' => now(),
            'updated_at' => now(),
        ]);

        DB::table('audit_logs')->insert([
            'pelaku' => "sensor:$sensor",
            'aksi' => $cmd->aksi.' '.$hasil,
            'target' => $cmd->target_ip,
            'incident_id' => $cmd->incident_id,
            'created_at' => now(),
        ]);

        return response()->json(['status' => 'ok']);
    }
}
