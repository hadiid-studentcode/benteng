<?php

namespace Tests\Feature;

use App\Http\Controllers\CommandController;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_renders_incident_with_playbook_and_quarantined_device_status(): void
    {
        $this->buatDnsEvent('192.168.1.142');
        $this->buatInsiden(['client_ip' => '192.168.1.142', 'status' => 'dikarantina', 'judul' => 'Beaconing ke c2-sim.example']);

        $response = $this->get(route('benteng.dashboard'));

        $response->assertOk();
        $response->assertSee('Beaconing ke c2-sim.example');
        $response->assertSee('Application Layer Protocol: DNS');
        $response->assertSee('Apa artinya &amp; langkah yang disarankan', false);
        $response->assertSee('<span class="pill karantina">Dikarantina</span>', false);
        $response->assertDontSee('<span class="pill aman">Normal</span>', false);
    }

    public function test_partial_request_returns_only_the_dynamic_fragment(): void
    {
        $this->buatInsiden(['judul' => 'Query domain acak (DGA)']);

        $response = $this->withHeader('X-Benteng-Sebagian', '1')->get(route('benteng.dashboard'));

        $response->assertOk();
        $response->assertSee('Query domain acak (DGA)');
        $response->assertDontSee('<html', false);
    }

    public function test_shows_sensor_offline_when_responder_heartbeat_is_stale(): void
    {
        $this->travelTo('2026-10-25 19:00:00');
        $this->buatDnsEvent('192.168.1.142');
        Cache::forever(CommandController::kunciDetak('BTG-02'), '2026-10-25 18:59:00');

        $response = $this->get(route('benteng.dashboard'));

        $response->assertOk();
        $response->assertSee('Tidak merespons');
    }

    public function test_quarantine_marks_every_active_incident_of_the_device_and_sends_one_command(): void
    {
        $beacon = $this->buatInsiden(['client_ip' => '192.168.1.142', 'jenis' => 'beaconing']);
        $dga = $this->buatInsiden(['client_ip' => '192.168.1.142', 'jenis' => 'dga']);
        $lain = $this->buatInsiden(['client_ip' => '192.168.1.150']);

        $response = $this->from(route('benteng.dashboard'))->post(route('benteng.karantina', $beacon));

        $response->assertRedirect(route('benteng.dashboard'));
        $response->assertSessionHas('pesan');

        $this->assertDatabaseHas('incidents', ['id' => $beacon, 'status' => 'dikarantina']);
        $this->assertDatabaseHas('incidents', ['id' => $dga, 'status' => 'dikarantina']);
        $this->assertDatabaseHas('incidents', ['id' => $lain, 'status' => 'baru']);

        $this->assertDatabaseCount('commands', 1);
        $this->assertDatabaseHas('commands', [
            'sensor' => 'BTG-02', 'aksi' => 'quarantine', 'target_ip' => '192.168.1.142',
            'incident_id' => $beacon, 'status' => 'pending',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'pelaku' => 'analis', 'aksi' => 'minta karantina', 'target' => '192.168.1.142', 'incident_id' => $beacon,
        ]);
    }

    public function test_quarantine_is_rejected_when_incident_is_already_quarantined(): void
    {
        $id = $this->buatInsiden(['status' => 'dikarantina']);

        $response = $this->from(route('benteng.dashboard'))->post(route('benteng.karantina', $id));

        $response->assertRedirect(route('benteng.dashboard'));
        $response->assertSessionHas('galat');

        $this->assertDatabaseCount('commands', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_quarantine_is_rejected_for_ipv6_device(): void
    {
        $id = $this->buatInsiden(['client_ip' => 'fd00::42']);

        $response = $this->from(route('benteng.dashboard'))->post(route('benteng.karantina', $id));

        $response->assertSessionHas('galat');

        $this->assertDatabaseHas('incidents', ['id' => $id, 'status' => 'baru']);
        $this->assertDatabaseCount('commands', 0);
    }

    public function test_release_reactivates_every_quarantined_incident_of_the_device(): void
    {
        $beacon = $this->buatInsiden(['client_ip' => '192.168.1.142', 'status' => 'dikarantina']);
        $dga = $this->buatInsiden(['client_ip' => '192.168.1.142', 'status' => 'dikarantina']);

        $response = $this->from(route('benteng.dashboard'))->post(route('benteng.lepas', $dga));

        $response->assertRedirect(route('benteng.dashboard'));
        $response->assertSessionHas('pesan');

        $this->assertDatabaseHas('incidents', ['id' => $beacon, 'status' => 'baru']);
        $this->assertDatabaseHas('incidents', ['id' => $dga, 'status' => 'baru']);

        $this->assertDatabaseCount('commands', 1);
        $this->assertDatabaseHas('commands', ['aksi' => 'unquarantine', 'target_ip' => '192.168.1.142', 'incident_id' => $dga]);

        $this->assertDatabaseHas('audit_logs', ['aksi' => 'lepas karantina', 'incident_id' => $dga]);
    }

    public function test_release_is_rejected_when_incident_is_not_quarantined(): void
    {
        $id = $this->buatInsiden(['status' => 'baru']);

        $response = $this->from(route('benteng.dashboard'))->post(route('benteng.lepas', $id));

        $response->assertSessionHas('galat');

        $this->assertDatabaseCount('commands', 0);
    }

    public function test_false_positive_changes_only_the_selected_incident(): void
    {
        $dipilih = $this->buatInsiden(['client_ip' => '192.168.1.142']);
        $lain = $this->buatInsiden(['client_ip' => '192.168.1.142']);

        $response = $this->from(route('benteng.dashboard'))->post(route('benteng.false-positive', $dipilih));

        $response->assertRedirect(route('benteng.dashboard'));

        $this->assertDatabaseHas('incidents', ['id' => $dipilih, 'status' => 'false_positive']);
        $this->assertDatabaseHas('incidents', ['id' => $lain, 'status' => 'baru']);
        $this->assertDatabaseCount('commands', 0);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'tandai false positive', 'incident_id' => $dipilih]);
    }

    public function test_returns_404_for_non_numeric_incident_id(): void
    {
        $response = $this->post('/dashboard/incidents/abc/karantina');

        $response->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $ubah
     */
    private function buatInsiden(array $ubah = []): int
    {
        return DB::table('incidents')->insertGetId(array_merge([
            'sensor' => 'BTG-02',
            'client_ip' => '192.168.1.142',
            'jenis' => 'beaconing',
            'tingkat' => 'kritis',
            'skor' => 90,
            'judul' => 'Insiden uji',
            'attck' => 'T1071.004',
            'bukti' => 'bukti uji',
            'status' => 'baru',
            'sidik' => fake()->unique()->uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $ubah));
    }

    private function buatDnsEvent(string $clientIp): void
    {
        DB::table('dns_events')->insert([
            'sensor' => 'BTG-02',
            'queried_at' => now(),
            'qtype' => 'A',
            'domain' => 'contoh.example',
            'client_ip' => $clientIp,
            'line_hash' => sha1(fake()->unique()->uuid()),
            'created_at' => now(),
        ]);
    }
}
