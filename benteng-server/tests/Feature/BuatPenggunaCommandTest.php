<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BuatPenggunaCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_viewer_account_with_given_password(): void
    {
        $this->artisan('benteng:pengguna danki@satuan.mil --nama="Kapten Andi" --peran=pengamat --sandi=rahasia-123')
            ->assertSuccessful();

        $user = User::firstWhere('email', 'danki@satuan.mil');
        $this->assertSame('Kapten Andi', $user->name);
        $this->assertSame('pengamat', $user->peran);
        $this->assertTrue(Hash::check('rahasia-123', $user->password));
    }

    public function test_rejects_unknown_role(): void
    {
        $this->artisan('benteng:pengguna danki@satuan.mil --peran=admin')
            ->expectsOutputToContain('Peran harus salah satu dari: analis, pengamat')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
