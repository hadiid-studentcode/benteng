<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tabel BENTENG memakai query builder tanpa model, jadi data uji dibuat lewat pembantu ini.
     *
     * @param  array<string, mixed>  $ubah
     */
    protected function buatInsiden(array $ubah = []): int
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

    /**
     * @param  array<string, mixed>  $ubah
     */
    protected function buatPerangkat(array $ubah = []): void
    {
        DB::table('perangkat')->insert(array_merge([
            'sensor' => 'BTG-02',
            'mac' => fake()->unique()->macAddress(),
            'ip' => '192.168.1.142',
            'hostname' => null,
            'dikenal' => true,
            'pertama_terlihat' => now(),
            'terakhir_terlihat' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $ubah));
    }

    protected function buatDnsEvent(string $clientIp, string $domain = 'contoh.example'): void
    {
        DB::table('dns_events')->insert([
            'sensor' => 'BTG-02',
            'queried_at' => now(),
            'qtype' => 'A',
            'domain' => $domain,
            'client_ip' => $clientIp,
            'line_hash' => sha1(fake()->unique()->uuid()),
            'created_at' => now(),
        ]);
    }
}
