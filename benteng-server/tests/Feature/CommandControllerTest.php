<?php

namespace Tests\Feature;

use App\Http\Controllers\CommandController;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommandControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_pulling_commands_records_sensor_heartbeat(): void
    {
        config(['benteng.sensor_token' => 'token-uji']);
        $this->travelTo('2026-10-25 19:00:00');

        $response = $this->withHeader('X-Sensor-Token', 'token-uji')->get('/api/sensors/BTG-02/commands');

        $response->assertOk();
        $this->assertSame('2026-10-25 19:00:00', Cache::get(CommandController::kunciDetak('BTG-02')));
    }

    public function test_sync_lists_only_quarantined_ipv4_devices_of_the_sensor(): void
    {
        config(['benteng.sensor_token' => 'token-uji']);
        $this->buatInsiden('BTG-02', '192.168.1.142', 'dikarantina');
        $this->buatInsiden('BTG-02', '192.168.1.142', 'dikarantina');
        $this->buatInsiden('BTG-02', '192.168.1.150', 'baru');
        $this->buatInsiden('BTG-03', '192.168.2.10', 'dikarantina');

        $response = $this->withHeader('X-Sensor-Token', 'token-uji')->get('/api/sensors/BTG-02/karantina');

        $response->assertOk();
        $this->assertSame("192.168.1.142\n", $response->getContent());
    }

    public function test_returns_401_and_records_no_heartbeat_when_token_is_wrong(): void
    {
        config(['benteng.sensor_token' => 'token-uji']);

        $response = $this->withHeader('X-Sensor-Token', 'salah')->get('/api/sensors/BTG-02/commands');

        $response->assertUnauthorized();
        $this->assertNull(Cache::get(CommandController::kunciDetak('BTG-02')));
    }

    private function buatInsiden(string $sensor, string $ip, string $status): void
    {
        DB::table('incidents')->insert([
            'sensor' => $sensor, 'client_ip' => $ip, 'jenis' => 'beaconing', 'tingkat' => 'kritis',
            'skor' => 90, 'judul' => 'Insiden uji', 'status' => $status, 'sidik' => fake()->unique()->uuid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
