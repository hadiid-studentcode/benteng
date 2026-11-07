<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mengosongkan data lab sebelum latihan demo atau rekaman video.
 * Insiden yang pernah ditandai "bukan ancaman" tidak dilaporkan ulang, jadi
 * simulator baru memunculkan insiden lagi setelah data lama dihapus.
 *
 *   php artisan benteng:reset-demo
 */
class ResetDemoCommand extends Command
{
    protected $signature = 'benteng:reset-demo {--force : jalankan tanpa konfirmasi}';

    protected $description = 'Kosongkan data DNS, insiden, perintah, jejak audit, dan daftar perangkat (akun tetap ada)';

    public function handle(): int
    {
        $pertanyaan = 'Semua data DNS, insiden, perintah, blokir domain, jejak audit, dan daftar perangkat akan dihapus. Akun tetap ada. Lanjutkan?';
        if (! $this->option('force') && ! $this->confirm($pertanyaan)) {
            $this->line('Dibatalkan, tidak ada data yang dihapus.');

            return self::SUCCESS;
        }

        $dikarantina = DB::table('incidents')
            ->where('status', 'dikarantina')
            ->get(['sensor', 'client_ip'])
            ->unique(fn ($i) => $i->sensor.'|'.$i->client_ip);

        DB::transaction(function () use ($dikarantina) {
            DB::table('dns_events')->delete();
            DB::table('incidents')->delete();
            DB::table('commands')->delete();
            DB::table('audit_logs')->delete();
            DB::table('perangkat')->delete();
            DB::table('domain_diblokir')->delete();

            // Perangkat yang masih dikarantina dilepas sensor, agar keadaan sensor sama dengan data.
            foreach ($dikarantina as $p) {
                DB::table('commands')->insert([
                    'sensor' => $p->sensor,
                    'aksi' => 'unquarantine',
                    'target_ip' => $p->client_ip,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $this->info('Data lab dikosongkan. Akun tidak diubah.');
        if ($dikarantina->isNotEmpty()) {
            $this->line($dikarantina->count().' perangkat akan dilepas dari karantina oleh sensor dalam beberapa detik.');
        }
        $this->line('Laporan DHCP berikutnya dari sensor menjadi daftar perangkat sah yang baru.');

        return self::SUCCESS;
    }
}
