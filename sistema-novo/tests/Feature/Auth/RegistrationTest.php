<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A rota publica /register foi desabilitada nesta etapa (Etapa 5
     * #42): sistema interno, sem necessidade de auto-cadastro publico.
     * Ver routes/auth.php para a analise completa e como reativar.
     */
    public function test_registration_screen_is_not_publicly_accessible(): void
    {
        $response = $this->get('/register');

        $response->assertNotFound();
    }

    public function test_new_users_can_register(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $component->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }
}
