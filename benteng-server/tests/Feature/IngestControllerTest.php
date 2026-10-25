<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class IngestControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_first_dhcp_report_becomes_baseline_of_known_devices(): void
    {
        config(['benteng.sensor_token' => 'token-uji']);

        $response = $this->kirimDhcp("1761450000 08:00:27:5e:aa:01 192.168.1.142 korban 01:08:00:27:5e:aa:01\n");

        $response->assertOk();
        $response->assertJson(['diterima' => 1, 'baru' => 1, 'baseline' => true]);
        $this->assertDatabaseHas('perangkat', [
            'sensor' => 'BTG-02', 'mac' => '08:00:27:5e:aa:01', 'ip' => '192.168.1.142',
            'hostname' => 'korban', 'dikenal' => true,
        ]);
    }

    public function test_device_appearing_after_baseline_is_unknown(): void
    {
        config(['benteng.sensor_token' => 'token-uji']);
        $this->kirimDhcp("1761450000 08:00:27:5e:aa:01 192.168.1.142 korban 01:08:00:27:5e:aa:01\n");

        $response = $this->kirimDhcp(
            "1761450000 08:00:27:5e:aa:01 192.168.1.142 korban 01:08:00:27:5e:aa:01\n"
            ."1761450600 0A:3C:91:7D:E2:4F 192.168.1.187 * 01:0a:3c:91:7d:e2:4f\n"
            ."baris rusak\n"
        );

        $response->assertJson(['diterima' => 2, 'baru' => 1, 'baseline' => false]);
        $this->assertDatabaseHas('perangkat', ['mac' => '0a:3c:91:7d:e2:4f', 'hostname' => null, 'dikenal' => false]);
        $this->assertDatabaseCount('perangkat', 2);
    }

    public function test_dhcp_returns_401_when_token_is_wrong(): void
    {
        config(['benteng.sensor_token' => 'token-uji']);

        $response = $this->call('POST', '/api/ingest/dhcp', [], [], [], [
            'HTTP_X-Sensor-Token' => 'salah', 'HTTP_X-Sensor-Id' => 'BTG-02', 'CONTENT_TYPE' => 'text/plain',
        ], "1761450000 08:00:27:5e:aa:01 192.168.1.142 korban *\n");

        $response->assertUnauthorized();
        $this->assertDatabaseCount('perangkat', 0);
    }

    private function kirimDhcp(string $isi): TestResponse
    {
        return $this->call('POST', '/api/ingest/dhcp', [], [], [], [
            'HTTP_X-Sensor-Token' => 'token-uji', 'HTTP_X-Sensor-Id' => 'BTG-02', 'CONTENT_TYPE' => 'text/plain',
        ], $isi);
    }
}
