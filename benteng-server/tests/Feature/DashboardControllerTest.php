<?php

namespace Tests\Feature;

use App\Http\Controllers\CommandController;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('benteng.dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_dashboard_highlights_active_incidents_with_action_button(): void
    {
        $id = $this->buatInsiden(['judul' => 'Beaconing ke c2-sim.example']);

        $response = $this->actingAs(User::factory()->create())->get(route('benteng.dashboard'));

        $response->assertOk();
        $response->assertSee('1 insiden perlu ditindaklanjuti');
        $response->assertSee('Beaconing ke c2-sim.example');
        $response->assertSee(route('benteng.karantina', $id));
        $response->assertSee('data-lencana="insiden"', false);
    }

    public function test_dashboard_warns_when_sensor_heartbeat_is_stale(): void
    {
        $this->travelTo('2026-10-25 19:00:00');
        $this->buatDnsEvent('192.168.1.142');
        Cache::forever(CommandController::kunciDetak('BTG-02'), '2026-10-25 18:59:00');

        $response = $this->actingAs(User::factory()->create())->get(route('benteng.dashboard'));

        $response->assertOk();
        $response->assertSee('Sensor BTG-02 tidak merespons');
    }

    public function test_partial_request_returns_only_the_dynamic_fragment(): void
    {
        $this->buatInsiden(['judul' => 'Query domain acak (DGA)']);

        $response = $this->actingAs(User::factory()->create())
            ->withHeader('X-Benteng-Sebagian', '1')
            ->get(route('benteng.dashboard'));

        $response->assertOk();
        $response->assertSee('Query domain acak (DGA)');
        $response->assertSee('data-segar-info', false);
        $response->assertDontSee('<html', false);
    }

    public function test_topology_shows_dhcp_device_and_hides_sensor_loopback(): void
    {
        $this->buatDnsEvent('127.0.0.1');
        $this->buatPerangkat(['ip' => '192.168.1.187', 'hostname' => 'laptop-tamu', 'dikenal' => false]);

        $response = $this->actingAs(User::factory()->create())->get(route('benteng.topologi'));

        $response->assertOk();
        $response->assertSee('192.168.1.187');
        $response->assertSee('Belum dikenal');
        $response->assertDontSee('127.0.0.1');
    }

    public function test_sensor_page_counts_pending_commands(): void
    {
        $this->buatDnsEvent('192.168.1.142');
        $this->buatInsiden(['status' => 'dikarantina']);
        DB::table('commands')->insert([
            'sensor' => 'BTG-02', 'aksi' => 'quarantine', 'target_ip' => '192.168.1.142',
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs(User::factory()->create())->get(route('benteng.sensor'));

        $response->assertOk();
        $response->assertSee('Sensor BTG-02');
        $response->assertSee('1 perintah');
    }
}
