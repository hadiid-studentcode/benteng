<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Membuat (atau memperbarui) akun dashboard.
 *
 *   php artisan benteng:pengguna analis@satuan.mil --nama="Serda Budi"
 *   php artisan benteng:pengguna danki@satuan.mil --nama="Kapten Andi" --peran=pengamat
 */
class BuatPenggunaCommand extends Command
{
    protected $signature = 'benteng:pengguna
        {email : email untuk masuk}
        {--nama= : nama tampilan yang tercatat di jejak audit}
        {--peran=analis : analis (bisa bertindak) atau pengamat (hanya melihat)}
        {--sandi= : kata sandi; kosongkan agar dibuatkan acak}';

    protected $description = 'Buat atau perbarui akun dashboard BENTENG';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $peran = (string) $this->option('peran');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Email tidak valid: $email");

            return self::FAILURE;
        }
        if (! array_key_exists($peran, User::PERAN)) {
            $this->error('Peran harus salah satu dari: '.implode(', ', array_keys(User::PERAN)));

            return self::FAILURE;
        }

        $sandiAcak = ! $this->option('sandi');
        $sandi = $sandiAcak ? Str::password(16, symbols: false) : (string) $this->option('sandi');

        $user = User::firstOrNew(['email' => $email]);
        $user->name = $this->option('nama') ?: ($user->name ?? Str::before($email, '@'));
        $user->peran = $peran;
        $user->password = $sandi;
        $user->save();

        $this->info(($user->wasRecentlyCreated ? 'Akun dibuat' : 'Akun diperbarui').": {$user->name} <{$user->email}> sebagai ".User::PERAN[$peran]);
        if ($sandiAcak) {
            $this->line("Kata sandi: $sandi");
            $this->comment('Simpan sekarang; kata sandi tidak bisa ditampilkan lagi.');
        }

        return self::SUCCESS;
    }
}
