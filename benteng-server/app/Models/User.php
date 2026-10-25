<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'peran', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Peran yang dikenal BENTENG beserta labelnya.
     */
    public const PERAN = [
        'analis' => 'Analis',
        'pengamat' => 'Pengamat',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Hanya analis yang boleh mengirim perintah ke sensor atau menutup insiden.
     * Pengamat (mis. pimpinan satuan) hanya bisa melihat.
     */
    public function bisaBertindak(): bool
    {
        return $this->peran === 'analis';
    }
}
