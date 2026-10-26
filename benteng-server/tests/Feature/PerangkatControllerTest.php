<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PerangkatControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_lists_dns_and_dhcp_devices_without_sensor_loopback(): void
    {
        $this->buatDnsEvent('192.168.1.142');
        $this->buatDnsEvent('127.0.0.1');
        $this->buatPerangkat(['ip' => '192.168.1.187', 'mac' => '0a:3c:91:7d:e2:4f', 'hostname' => 'laptop-tamu', 'dikenal' => false]);

        $response = $this->actingAs(User::factory()->create())->get(route('benteng.perangkat'));

        $response->assertOk();
        $response->assertSee('192.168.1.142');
        $response->assertSee('0a:3c:91:7d:e2:4f');
        $response->assertSee('Belum dikenal');
        $response->assertDontSee('127.0.0.1');
    }

    public function test_unknown_filter_shows_only_unregistered_devices(): void
    {
        $this->buatPerangkat(['ip' => '192.168.1.142', 'hostname' => 'korban', 'dikenal' => true]);
        $this->buatPerangkat(['ip' => '192.168.1.187', 'hostname' => 'laptop-tamu', 'dikenal' => false]);

        $response = $this->actingAs(User::factory()->create())->get(route('benteng.perangkat', ['status' => 'asing']));

        $response->assertOk();
        $response->assertSee('laptop-tamu');
        $response->assertDontSee('korban');
    }

    public function test_device_with_active_incident_links_to_it(): void
    {
        $this->buatDnsEvent('192.168.1.142');
        $id = $this->buatInsiden(['client_ip' => '192.168.1.142']);

        $response = $this->actingAs(User::factory()->create())->get(route('benteng.perangkat'));

        $response->assertSee(route('benteng.insiden.detail', $id));
    }
}
