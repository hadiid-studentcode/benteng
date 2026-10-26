<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InsidenControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_index_filters_incidents_by_status(): void
    {
        $this->buatInsiden(['judul' => 'Insiden aktif uji']);
        $this->buatInsiden(['judul' => 'Insiden karantina uji', 'status' => 'dikarantina']);

        $response = $this->actingAs(User::factory()->create())->get(route('benteng.insiden', ['status' => 'dikarantina']));

        $response->assertOk();
        $response->assertSee('Insiden karantina uji');
        $response->assertDontSee('Insiden aktif uji');
    }

    public function test_detail_shows_playbook_evidence_and_detection_timeline(): void
    {
        $id = $this->buatInsiden([
            'judul' => 'Beaconing ke c2-sim.example dari 192.168.1.142',
            'bukti' => '11 jeda konsisten sekitar 31 dtk',
            'mulai_pada' => '2026-10-25 18:54:48',
            'created_at' => '2026-10-25 19:06:13',
        ]);

        $response = $this->actingAs(User::factory()->create())->get(route('benteng.insiden.detail', $id));

        $response->assertOk();
        $response->assertSeeInOrder(['Apa yang terjadi', 'Bukti', 'Langkah penanganan yang disarankan']);
        $response->assertSee('11 jeda konsisten sekitar 31 dtk');
        $response->assertSee('Application Layer Protocol: DNS');
        $response->assertSeeInOrder(['Aktivitas mencurigakan pertama', 'Insiden terdeteksi']);
    }

    public function test_detail_returns_404_for_unknown_incident(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('benteng.insiden.detail', 999));

        $response->assertNotFound();
    }

    public function test_viewer_sees_no_action_buttons(): void
    {
        $id = $this->buatInsiden();

        $response = $this->actingAs(User::factory()->pengamat()->create())->get(route('benteng.insiden.detail', $id));

        $response->assertOk();
        $response->assertSee('Akun pengamat hanya dapat melihat');
        $response->assertDontSee(route('benteng.karantina', $id));
        $response->assertDontSee(route('benteng.false-positive', $id));
    }

    public function test_viewer_cannot_quarantine(): void
    {
        $id = $this->buatInsiden();

        $response = $this->actingAs(User::factory()->pengamat()->create())->post(route('benteng.karantina', $id));

        $response->assertForbidden();

        $this->assertDatabaseHas('incidents', ['id' => $id, 'status' => 'baru']);
        $this->assertDatabaseCount('commands', 0);
    }

    public function test_quarantine_marks_every_active_incident_of_the_device_and_sends_one_command(): void
    {
        $analis = User::factory()->create(['name' => 'Serda Budi']);
        $beacon = $this->buatInsiden(['client_ip' => '192.168.1.142', 'jenis' => 'beaconing']);
        $dga = $this->buatInsiden(['client_ip' => '192.168.1.142', 'jenis' => 'dga']);
        $lain = $this->buatInsiden(['client_ip' => '192.168.1.150']);

        $response = $this->actingAs($analis)->from(route('benteng.insiden'))->post(route('benteng.karantina', $beacon));

        $response->assertRedirect(route('benteng.insiden'));
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
            'pelaku' => 'Serda Budi', 'user_id' => $analis->id, 'aksi' => 'minta karantina',
            'target' => '192.168.1.142', 'incident_id' => $beacon,
        ]);
    }

    public function test_quarantine_shows_in_detail_timeline_with_analyst_name(): void
    {
        $analis = User::factory()->create(['name' => 'Serda Budi']);
        $id = $this->buatInsiden(['created_at' => now()->subMinute()]);
        $this->actingAs($analis)->post(route('benteng.karantina', $id));

        $response = $this->actingAs($analis)->get(route('benteng.insiden.detail', $id));

        $response->assertSeeInOrder(['Karantina diminta', 'Oleh Serda Budi', 'Menunggu sensor']);
    }

    public function test_quarantine_is_rejected_when_incident_is_already_quarantined(): void
    {
        $id = $this->buatInsiden(['status' => 'dikarantina']);

        $response = $this->actingAs(User::factory()->create())->from(route('benteng.insiden'))->post(route('benteng.karantina', $id));

        $response->assertRedirect(route('benteng.insiden'));
        $response->assertSessionHas('galat');

        $this->assertDatabaseCount('commands', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_quarantine_is_rejected_for_ipv6_device(): void
    {
        $id = $this->buatInsiden(['client_ip' => 'fd00::42']);

        $response = $this->actingAs(User::factory()->create())->post(route('benteng.karantina', $id));

        $response->assertSessionHas('galat');

        $this->assertDatabaseHas('incidents', ['id' => $id, 'status' => 'baru']);
        $this->assertDatabaseCount('commands', 0);
    }

    public function test_release_reactivates_every_quarantined_incident_of_the_device(): void
    {
        $beacon = $this->buatInsiden(['client_ip' => '192.168.1.142', 'status' => 'dikarantina']);
        $dga = $this->buatInsiden(['client_ip' => '192.168.1.142', 'status' => 'dikarantina']);

        $response = $this->actingAs(User::factory()->create())->from(route('benteng.insiden'))->post(route('benteng.lepas', $dga));

        $response->assertRedirect(route('benteng.insiden'));
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

        $response = $this->actingAs(User::factory()->create())->post(route('benteng.lepas', $id));

        $response->assertSessionHas('galat');

        $this->assertDatabaseCount('commands', 0);
    }

    public function test_not_a_threat_closes_only_the_selected_incident(): void
    {
        $dipilih = $this->buatInsiden(['client_ip' => '192.168.1.142']);
        $lain = $this->buatInsiden(['client_ip' => '192.168.1.142']);

        $response = $this->actingAs(User::factory()->create())->post(route('benteng.false-positive', $dipilih));

        $response->assertSessionHas('pesan', "Insiden #$dipilih ditandai bukan ancaman.");

        $this->assertDatabaseHas('incidents', ['id' => $dipilih, 'status' => 'false_positive']);
        $this->assertDatabaseHas('incidents', ['id' => $lain, 'status' => 'baru']);
        $this->assertDatabaseCount('commands', 0);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'tandai false positive', 'incident_id' => $dipilih]);
    }

    public function test_registering_foreign_device_marks_it_known(): void
    {
        $this->buatPerangkat(['mac' => '0a:3c:91:7d:e2:4f', 'ip' => '192.168.1.187', 'dikenal' => false]);
        $id = $this->buatInsiden([
            'client_ip' => '192.168.1.187', 'jenis' => 'perangkat_asing', 'attck' => 'T1200',
            'sidik' => 'asing|BTG-02|0a:3c:91:7d:e2:4f',
        ]);

        $response = $this->actingAs(User::factory()->create())->post(route('benteng.false-positive', $id));

        $response->assertSessionHas('pesan', 'Perangkat 192.168.1.187 didaftarkan sebagai perangkat sah.');

        $this->assertDatabaseHas('perangkat', ['mac' => '0a:3c:91:7d:e2:4f', 'dikenal' => true]);
        $this->assertDatabaseHas('incidents', ['id' => $id, 'status' => 'false_positive']);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'daftarkan perangkat', 'incident_id' => $id]);
    }

    public function test_returns_404_for_non_numeric_incident_id(): void
    {
        $response = $this->actingAs(User::factory()->create())->post('/insiden/abc/karantina');

        $response->assertNotFound();
        $this->assertSame(0, DB::table('commands')->count());
    }
}
