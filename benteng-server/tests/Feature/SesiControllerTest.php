<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SesiControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_credentials_log_in_and_redirect_to_dashboard(): void
    {
        $user = User::factory()->create(['email' => 'analis@satuan.mil', 'password' => 'sandi-kuat-123']);

        $response = $this->post('/login', ['email' => 'analis@satuan.mil', 'password' => 'sandi-kuat-123']);

        $response->assertRedirect(route('benteng.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_returns_error_and_stays_logged_out(): void
    {
        User::factory()->create(['email' => 'analis@satuan.mil', 'password' => 'sandi-kuat-123']);

        $response = $this->from('/login')->post('/login', ['email' => 'analis@satuan.mil', 'password' => 'salah']);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email' => 'Email atau kata sandi salah.']);
        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'analis@satuan.mil', 'password' => 'salah']);
        }

        $response = $this->post('/login', ['email' => 'analis@satuan.mil', 'password' => 'salah']);

        $response->assertTooManyRequests();
    }

    public function test_logout_ends_session(): void
    {
        $response = $this->actingAs(User::factory()->create())->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
