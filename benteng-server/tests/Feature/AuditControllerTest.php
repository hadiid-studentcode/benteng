<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sensor_filter_hides_analyst_actions(): void
    {
        DB::table('audit_logs')->insert([
            ['pelaku' => 'Serda Budi', 'aksi' => 'minta karantina', 'target' => '192.168.1.142', 'created_at' => now()],
            ['pelaku' => 'sensor:BTG-02', 'aksi' => 'quarantine ok', 'target' => '192.168.1.142', 'created_at' => now()],
        ]);

        $response = $this->actingAs(User::factory()->create())->get(route('benteng.audit', ['pelaku' => 'sensor']));

        $response->assertOk();
        $response->assertSee('Menerapkan karantina');
        $response->assertDontSee('Serda Budi');
    }

    public function test_paginates_after_25_entries(): void
    {
        DB::table('audit_logs')->insert(collect(range(1, 30))->map(fn ($n) => [
            'pelaku' => 'Serda Budi', 'aksi' => 'minta karantina', 'target' => "10.0.0.$n", 'created_at' => now(),
        ])->all());

        $response = $this->actingAs(User::factory()->create())->get(route('benteng.audit'));

        $response->assertOk();
        $response->assertSee('Halaman 1 dari 2, total 30 catatan');
    }
}
