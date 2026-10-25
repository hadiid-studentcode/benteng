<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Halaman awal langsung membuka dashboard.
     */
    public function test_root_redirects_to_dashboard(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/dashboard');
    }
}
