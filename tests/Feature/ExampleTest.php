<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_login_uses_https_behind_a_proxy(): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.10'])
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get('http://sanatorio.example/');

        $response->assertOk()
            ->assertSee('href="https://sanatorio.example/css/estilos.css"', false)
            ->assertSee('src="https://sanatorio.example/imagenes/logo-sanatorio.jpg"', false)
            ->assertSee('action="https://sanatorio.example/login"', false);
    }

    public function test_local_login_keeps_http_without_a_proxy(): void
    {
        $response = $this->get('http://localhost/');

        $response->assertOk()
            ->assertSee('href="http://localhost/css/estilos.css"', false)
            ->assertSee('action="http://localhost/login"', false);
    }
}
