<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResetDemoCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_clears_lab_data_keeps_accounts_and_releases_quarantined_device(): void
    {
        $user = User::factory()->create();
        $this->buatDnsEvent('192.168.1.142');
        $this->buatPerangkat();
        $this->buatInsiden(['client_ip' => '192.168.1.142', 'status' => 'dikarantina']);
        $this->buatInsiden(['client_ip' => '192.168.1.142', 'status' => 'dikarantina']);
        $this->buatInsiden(['client_ip' => '192.168.1.150', 'status' => 'false_positive']);
        DB::table('audit_logs')->insert(['pelaku' => 'Serda Budi', 'aksi' => 'minta karantina', 'created_at' => now()]);

        $this->artisan('benteng:reset-demo --force')->assertSuccessful();

        $this->assertDatabaseCount('dns_events', 0);
        $this->assertDatabaseCount('incidents', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('perangkat', 0);
        $this->assertModelExists($user);

        $this->assertDatabaseCount('commands', 1);
        $this->assertDatabaseHas('commands', [
            'sensor' => 'BTG-02', 'aksi' => 'unquarantine', 'target_ip' => '192.168.1.142', 'status' => 'pending',
        ]);
    }

    public function test_declining_confirmation_keeps_data(): void
    {
        $this->buatInsiden();

        $this->artisan('benteng:reset-demo')
            ->expectsConfirmation('Semua data DNS, insiden, perintah, blokir domain, jejak audit, dan daftar perangkat akan dihapus. Akun tetap ada. Lanjutkan?', 'no')
            ->expectsOutput('Dibatalkan, tidak ada data yang dihapus.')
            ->assertSuccessful();

        $this->assertDatabaseCount('incidents', 1);
    }
}
