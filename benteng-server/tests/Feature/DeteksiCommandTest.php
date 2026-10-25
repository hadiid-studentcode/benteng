<?php

namespace Tests\Feature;

use App\Console\Commands\DeteksiCommand;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeteksiCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_detects_beacon_with_jitter_and_records_first_evidence_time(): void
    {
        // Beacon tiap ~45 dtk dengan jitter +-20%.
        $jeda = [40, 50, 45, 38, 52, 47, 43, 54, 41, 46, 49, 37, 44, 51];
        $this->buatQueryBerjeda('192.168.1.142', 'update-cek.example', '2026-10-25 08:00:00', $jeda);

        $this->artisan('benteng:deteksi')->assertSuccessful();

        $this->assertDatabaseHas('incidents', [
            'jenis' => 'beaconing', 'client_ip' => '192.168.1.142', 'attck' => 'T1071.004',
            'mulai_pada' => '2026-10-25 08:00:00', 'sidik' => 'beacon|BTG-02|192.168.1.142|update-cek.example',
        ]);
    }

    public function test_irregular_human_access_is_not_reported_as_beacon(): void
    {
        $jeda = [5, 120, 33, 260, 14, 90, 400, 61, 7, 180, 25, 300, 48, 72, 150, 11];
        $this->buatQueryBerjeda('192.168.1.142', 'www.detik.com', '2026-10-25 08:00:00', $jeda);

        $this->artisan('benteng:deteksi')->assertSuccessful();

        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_detects_random_registered_domains_as_dga_but_not_random_cdn_subdomains(): void
    {
        $dga = ['xkqjzvbwplrtm.com', 'qwrtzpkhgfdsl.net', 'mnbvcxzlkjhgf.org', 'plmkonjibhuvg.info',
            'zxcvbnmasdfgh.com', 'trwqpklmnbvcx.net', 'hgfdsapoiuytr.org', 'bvcxzqwrtplkj.com'];
        $cdn = ['d1nt6ywwhy4kxl.cloudfront.net', 'd3c9xq7zkt2wpb.cloudfront.net', 'dk8vq2mzx7rtpl.cloudfront.net',
            'd2xkq9wzv7bmtn.cloudfront.net', 'd7zrq2kxv9wpmt.cloudfront.net', 'd9wqk2xzv7trbm.cloudfront.net',
            'd4zkx9qwv2mtrb.cloudfront.net', 'd6qxz2kwv9rbmt.cloudfront.net'];
        foreach ($dga as $n => $domain) {
            $this->buatQuery('192.168.1.142', $domain, date('Y-m-d H:i:s', strtotime('2026-10-25 08:00:00') + $n * 2));
        }
        foreach ($cdn as $n => $domain) {
            $this->buatQuery('192.168.1.150', $domain, date('Y-m-d H:i:s', strtotime('2026-10-25 08:00:00') + $n * 2));
        }

        $this->artisan('benteng:deteksi')->assertSuccessful();

        $this->assertDatabaseHas('incidents', ['jenis' => 'dga', 'client_ip' => '192.168.1.142']);
        $this->assertDatabaseMissing('incidents', ['client_ip' => '192.168.1.150']);
    }

    public function test_reports_unknown_dhcp_device_once(): void
    {
        DB::table('perangkat')->insert([
            ['sensor' => 'BTG-02', 'mac' => '08:00:27:5e:aa:01', 'ip' => '192.168.1.142', 'hostname' => 'korban', 'dikenal' => true,
                'pertama_terlihat' => '2026-10-25 08:00:00', 'terakhir_terlihat' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['sensor' => 'BTG-02', 'mac' => '0a:3c:91:7d:e2:4f', 'ip' => '192.168.1.187', 'hostname' => 'laptop-tamu', 'dikenal' => false,
                'pertama_terlihat' => '2026-10-25 09:00:00', 'terakhir_terlihat' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->artisan('benteng:deteksi')->assertSuccessful();
        $this->artisan('benteng:deteksi')->assertSuccessful();

        $this->assertDatabaseCount('incidents', 1);
        $this->assertDatabaseHas('incidents', [
            'jenis' => 'perangkat_asing', 'client_ip' => '192.168.1.187', 'attck' => 'T1200',
            'mulai_pada' => '2026-10-25 09:00:00', 'sidik' => 'asing|BTG-02|0a:3c:91:7d:e2:4f',
        ]);
    }

    public function test_records_detector_heartbeat(): void
    {
        $this->travelTo('2026-10-25 19:00:00');

        $this->artisan('benteng:deteksi')->assertSuccessful();

        $this->assertSame('2026-10-25 19:00:00', Cache::get(DeteksiCommand::KUNCI_DETAK));
    }

    public function test_evaluation_meets_all_expectations_without_touching_database(): void
    {
        $this->artisan('benteng:deteksi --evaluasi')
            ->expectsOutputToContain('Alarm palsu trafik normal: 0/3')
            ->assertSuccessful();

        $this->assertDatabaseCount('incidents', 0);
    }

    /**
     * @param  array<int, int>  $jeda
     */
    private function buatQueryBerjeda(string $client, string $domain, string $mulai, array $jeda): void
    {
        $t = strtotime($mulai);
        $this->buatQuery($client, $domain, date('Y-m-d H:i:s', $t));
        foreach ($jeda as $d) {
            $t += $d;
            $this->buatQuery($client, $domain, date('Y-m-d H:i:s', $t));
        }
    }

    private function buatQuery(string $client, string $domain, string $waktu): void
    {
        DB::table('dns_events')->insert([
            'sensor' => 'BTG-02', 'queried_at' => $waktu, 'qtype' => 'A', 'domain' => $domain,
            'client_ip' => $client, 'line_hash' => sha1(fake()->unique()->uuid()), 'created_at' => now(),
        ]);
    }
}
