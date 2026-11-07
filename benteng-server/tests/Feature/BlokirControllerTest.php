<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BlokirControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_blocking_from_beacon_incident_handles_it_without_disconnecting_device(): void
    {
        $analis = User::factory()->create(['name' => 'Serda Budi']);
        $id = $this->buatInsiden(['sidik' => 'beacon|BTG-02|192.168.1.142|c2-sim.example']);

        $response = $this->actingAs($analis)->post(route('benteng.blokir.insiden', $id));

        $response->assertSessionHas('pesan');
        $this->assertDatabaseHas('domain_diblokir', ['sensor' => 'BTG-02', 'domain' => 'c2-sim.example', 'incident_id' => $id, 'diterapkan_pada' => null]);
        $this->assertDatabaseHas('incidents', ['id' => $id, 'status' => 'diblokir']);
        $this->assertDatabaseCount('commands', 0);
        $this->assertDatabaseHas('audit_logs', ['pelaku' => 'Serda Budi', 'aksi' => 'blokir domain', 'target' => 'c2-sim.example', 'incident_id' => $id]);
    }

    public function test_blocking_keeps_quarantine_status(): void
    {
        $id = $this->buatInsiden(['status' => 'dikarantina', 'sidik' => 'beacon|BTG-02|192.168.1.142|c2-sim.example']);

        $this->actingAs(User::factory()->create())->post(route('benteng.blokir.insiden', $id));

        $this->assertDatabaseHas('incidents', ['id' => $id, 'status' => 'dikarantina']);
        $this->assertDatabaseHas('domain_diblokir', ['domain' => 'c2-sim.example']);
    }

    public function test_incident_without_domain_cannot_be_blocked(): void
    {
        $id = $this->buatInsiden(['jenis' => 'dga', 'sidik' => 'dga|BTG-02|192.168.1.142']);

        $response = $this->actingAs(User::factory()->create())->post(route('benteng.blokir.insiden', $id));

        $response->assertSessionHas('galat');
        $this->assertDatabaseCount('domain_diblokir', 0);
    }

    public function test_viewer_cannot_block(): void
    {
        $id = $this->buatInsiden(['sidik' => 'beacon|BTG-02|192.168.1.142|c2-sim.example']);

        $response = $this->actingAs(User::factory()->pengamat()->create())->post(route('benteng.blokir.insiden', $id));

        $response->assertForbidden();
        $this->assertDatabaseCount('domain_diblokir', 0);
    }

    public function test_release_reactivates_incident(): void
    {
        $id = $this->buatInsiden(['status' => 'diblokir']);
        $blokir = $this->buatBlokir(['incident_id' => $id]);

        $response = $this->actingAs(User::factory()->create())->post(route('benteng.blokir.lepas', $blokir));

        $response->assertSessionHas('pesan');
        $this->assertDatabaseCount('domain_diblokir', 0);
        $this->assertDatabaseHas('incidents', ['id' => $id, 'status' => 'baru']);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'lepas blokir domain']);
    }

    public function test_manual_block_normalizes_domain_and_rejects_invalid_input(): void
    {
        $this->buatDnsEvent('192.168.1.142');
        $analis = User::factory()->create();

        $this->actingAs($analis)->post(route('benteng.blokir.tambah'), ['domain' => ' Server-C2.Example ', 'sensor' => 'BTG-02'])->assertSessionHas('pesan');
        $this->assertDatabaseHas('domain_diblokir', ['domain' => 'server-c2.example']);

        foreach (["evil.example\naddress=/x/", 'tanpa-titik', '-buruk.example'] as $salah) {
            $this->actingAs($analis)->post(route('benteng.blokir.tambah'), ['domain' => $salah, 'sensor' => 'BTG-02'])->assertSessionHas('galat');
        }
        $this->assertDatabaseCount('domain_diblokir', 1);
    }

    public function test_page_counts_attempts_after_block(): void
    {
        $this->travelTo('2026-11-07 10:00:00');
        $this->buatBlokir(['domain' => 'c2-sim.example']);
        $this->buatDnsEvent('192.168.1.150', 'c2-sim.example');
        $this->buatDnsEvent('192.168.1.151', 'sub.c2-sim.example');

        $response = $this->actingAs(User::factory()->create())->get(route('benteng.blokir'));

        $response->assertOk();
        $response->assertSee('c2-sim.example');
        $response->assertSee('2 query dari 2 perangkat');
    }

    public function test_sensor_gets_blocklist_with_marker_even_when_empty(): void
    {
        config(['benteng.sensor_token' => 'token-uji']);

        $response = $this->withHeader('X-Sensor-Token', 'token-uji')->get('/api/sensors/BTG-02/blokir');

        $response->assertOk();
        $this->assertSame("#benteng 0 0\n", $response->getContent());
    }

    public function test_sensor_ack_marks_blocks_applied_and_ignores_newer_ones(): void
    {
        config(['benteng.sensor_token' => 'token-uji']);
        $lama = $this->buatBlokir(['domain' => 'lama.example']);
        $baru = $this->buatBlokir(['domain' => 'baru.example']);
        $this->buatBlokir(['sensor' => 'BTG-03', 'domain' => 'lain.example']);

        $daftar = $this->withHeader('X-Sensor-Token', 'token-uji')->get('/api/sensors/BTG-02/blokir');
        $this->assertSame("#benteng $baru 2\n$lama lama.example\n$baru baru.example\n", $daftar->getContent());

        $this->withHeader('X-Sensor-Token', 'token-uji')->post('/api/sensors/BTG-02/blokir/ack', ['sampai' => $lama])->assertOk();

        $this->assertDatabaseMissing('domain_diblokir', ['id' => $lama, 'diterapkan_pada' => null]);
        $this->assertDatabaseHas('domain_diblokir', ['id' => $baru, 'diterapkan_pada' => null]);
        $this->assertDatabaseHas('audit_logs', ['pelaku' => 'sensor:BTG-02', 'aksi' => 'blokir ok', 'target' => 'lama.example']);
    }

    public function test_blocklist_returns_401_when_token_is_wrong(): void
    {
        config(['benteng.sensor_token' => 'token-uji']);

        $this->withHeader('X-Sensor-Token', 'salah')->get('/api/sensors/BTG-02/blokir')->assertUnauthorized();
    }

    public function test_detector_reports_other_devices_trying_blocked_domain_once(): void
    {
        $this->travelTo('2026-11-07 10:00:00');
        $id = $this->buatInsiden(['client_ip' => '192.168.1.142', 'status' => 'diblokir']);
        $this->buatBlokir(['domain' => 'c2-sim.example', 'incident_id' => $id]);
        $this->travelTo('2026-11-07 10:05:00');
        $this->buatDnsEvent('192.168.1.142', 'c2-sim.example');
        $this->buatDnsEvent('192.168.1.150', 'c2-sim.example');
        $this->buatDnsEvent('127.0.0.1', 'c2-sim.example');

        $this->artisan('benteng:deteksi')->assertSuccessful();
        $this->artisan('benteng:deteksi')->assertSuccessful();

        $this->assertSame(1, DB::table('incidents')->where('jenis', 'domain_terblokir')->count());
        $this->assertDatabaseHas('incidents', ['jenis' => 'domain_terblokir', 'client_ip' => '192.168.1.150', 'mulai_pada' => '2026-11-07 10:05:00']);
    }

    /**
     * @param  array<string, mixed>  $ubah
     */
    private function buatBlokir(array $ubah = []): int
    {
        return DB::table('domain_diblokir')->insertGetId(array_merge([
            'sensor' => 'BTG-02',
            'domain' => 'c2-sim.example',
            'pelaku' => 'Serda Budi',
            'created_at' => now(),
            'updated_at' => now(),
        ], $ubah));
    }
}
