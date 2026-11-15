<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ModeDemoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function aktifkanModeDemo(): void
    {
        // Token dan mode sudah terisi, jadi perintah tidak menulis berkas .env selama test.
        config(['benteng.mode_demo' => true, 'benteng.sensor_token' => 'token-uji']);
    }

    public function test_login_page_shows_demo_accounts_only_in_demo_mode(): void
    {
        $this->aktifkanModeDemo();
        $this->get(route('login'))->assertOk()
            ->assertSee('Akun demo untuk juri')
            ->assertSee('analis@demo.local')
            ->assertSee('pengamat-demo');

        config(['benteng.mode_demo' => false]);
        $this->get(route('login'))->assertOk()
            ->assertDontSee('Akun demo untuk juri')
            ->assertDontSee('analis@demo.local');
    }

    public function test_setup_refuses_to_overwrite_existing_lab_data(): void
    {
        config(['benteng.mode_demo' => false]);
        $id = $this->buatInsiden();

        $this->artisan('benteng:siapkan-demo')->assertFailed();

        $this->assertDatabaseHas('incidents', ['id' => $id]);
        $this->assertDatabaseMissing('users', ['email' => 'analis@demo.local']);
    }

    public function test_setup_creates_demo_accounts_and_simulated_incidents(): void
    {
        $this->aktifkanModeDemo();
        $this->buatInsiden(['judul' => 'Insiden lama']);

        $this->artisan('benteng:siapkan-demo')->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'analis@demo.local', 'peran' => 'analis']);
        $this->assertDatabaseHas('users', ['email' => 'pengamat@demo.local', 'peran' => 'pengamat']);
        $this->assertDatabaseMissing('incidents', ['judul' => 'Insiden lama']);
        $this->assertDatabaseHas('incidents', ['jenis' => 'beaconing', 'client_ip' => '192.168.1.142', 'status' => 'baru']);
        $this->assertDatabaseMissing('incidents', ['client_ip' => '192.168.1.203']);
        $this->assertDatabaseHas('incidents', ['jenis' => 'dga', 'client_ip' => '192.168.1.142']);
        $this->assertDatabaseHas('incidents', ['jenis' => 'perangkat_asing', 'client_ip' => '192.168.1.214']);
        $this->assertDatabaseCount('commands', 0);

        $this->post('/login', ['email' => 'analis@demo.local', 'password' => 'analis-demo'])
            ->assertRedirect(route('benteng.dashboard'));
    }

    public function test_simulated_sensor_applies_pending_quarantine_and_block(): void
    {
        $this->aktifkanModeDemo();
        $insiden = $this->buatInsiden(['status' => 'dikarantina']);
        DB::table('commands')->insert([
            'sensor' => 'BTG-02', 'aksi' => 'quarantine', 'target_ip' => '192.168.1.142', 'incident_id' => $insiden,
            'status' => 'pending', 'created_at' => now()->subSeconds(5), 'updated_at' => now()->subSeconds(5),
        ]);
        DB::table('domain_diblokir')->insert([
            'sensor' => 'BTG-02', 'domain' => 'c2-sim.example', 'incident_id' => $insiden, 'pelaku' => 'Analis Demo',
            'created_at' => now()->subSeconds(5), 'updated_at' => now()->subSeconds(5),
        ]);

        $this->artisan('benteng:sensor-tiruan')->assertSuccessful();

        $this->assertDatabaseHas('commands', ['target_ip' => '192.168.1.142', 'status' => 'selesai', 'hasil' => 'ok']);
        $this->assertDatabaseMissing('domain_diblokir', ['domain' => 'c2-sim.example', 'diterapkan_pada' => null]);
        $this->assertDatabaseHas('audit_logs', ['pelaku' => 'sensor:BTG-02', 'aksi' => 'quarantine ok']);
        $this->assertDatabaseHas('audit_logs', ['pelaku' => 'sensor:BTG-02', 'aksi' => 'blokir ok']);
    }

    public function test_dashboard_runs_simulated_sensor_only_in_demo_mode(): void
    {
        $user = User::factory()->create();
        DB::table('commands')->insert([
            'sensor' => 'BTG-02', 'aksi' => 'quarantine', 'target_ip' => '192.168.1.142',
            'status' => 'pending', 'created_at' => now()->subSeconds(5), 'updated_at' => now()->subSeconds(5),
        ]);

        $this->actingAs($user)->get(route('benteng.dashboard'))->assertOk();
        $this->assertDatabaseHas('commands', ['status' => 'pending']);

        $this->aktifkanModeDemo();
        $this->actingAs($user)->get(route('benteng.dashboard'))->assertOk()->assertSee('Sensor BTG-02');
        $this->assertDatabaseHas('commands', ['status' => 'selesai']);
    }

    public function test_simulated_infected_pc_is_detected_as_beaconing_and_dga(): void
    {
        $this->aktifkanModeDemo();
        $analis = User::factory()->create(['peran' => 'analis']);

        $this->actingAs($analis)
            ->post(route('benteng.simulasi'), ['jenis' => 'terinfeksi'])
            ->assertSessionHas('pesan');
        $this->artisan('benteng:sensor-tiruan')->assertSuccessful();

        $this->assertDatabaseHas('perangkat', ['ip' => '192.168.1.150', 'dikenal' => true]);
        $this->assertDatabaseHas('incidents', ['jenis' => 'beaconing', 'client_ip' => '192.168.1.150', 'status' => 'baru']);
        $this->assertDatabaseHas('incidents', ['jenis' => 'dga', 'client_ip' => '192.168.1.150']);
    }

    public function test_simulated_unknown_device_is_reported(): void
    {
        $this->aktifkanModeDemo();
        $analis = User::factory()->create(['peran' => 'analis']);

        $this->actingAs($analis)->post(route('benteng.simulasi'), ['jenis' => 'asing']);
        $this->artisan('benteng:sensor-tiruan')->assertSuccessful();

        $this->assertDatabaseHas('incidents', ['jenis' => 'perangkat_asing', 'client_ip' => '192.168.1.150']);
    }

    public function test_simulation_is_unavailable_outside_demo_mode_and_for_observers(): void
    {
        config(['benteng.mode_demo' => false]);
        $analis = User::factory()->create(['peran' => 'analis']);
        $this->actingAs($analis)->post(route('benteng.simulasi'), ['jenis' => 'asing'])->assertNotFound();

        $this->aktifkanModeDemo();
        $pengamat = User::factory()->create(['peran' => 'pengamat']);
        $this->actingAs($pengamat)->post(route('benteng.simulasi'), ['jenis' => 'asing'])->assertForbidden();

        $this->assertDatabaseCount('perangkat', 0);
    }

    public function test_simulated_sensor_refuses_outside_demo_mode(): void
    {
        config(['benteng.mode_demo' => false]);

        $this->artisan('benteng:sensor-tiruan')->assertFailed();
    }
}
