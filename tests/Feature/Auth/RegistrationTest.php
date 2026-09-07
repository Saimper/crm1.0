<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * El alta pública está deliberadamente cerrada: las cuentas se crean por SSO
 * o por administración. Estos tests son la guardia que impide reabrirla sin
 * darse cuenta (por ejemplo al re-publicar el scaffolding de Laravel Breeze).
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_ruta_de_registro_no_existe(): void
    {
        $this->assertFalse(
            Route::has('register'),
            'La ruta "register" volvió a registrarse: el alta pública debe permanecer cerrada.'
        );
    }

    public function test_la_pantalla_de_registro_no_es_alcanzable(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_el_componente_volt_de_registro_no_existe(): void
    {
        $this->assertFileDoesNotExist(
            resource_path('views/livewire/pages/auth/register.blade.php'),
            'El componente de registro sigue en el repositorio: aunque no haya ruta, '
            .'un snapshot de Livewire previamente emitido podría seguir invocándolo.'
        );
    }
}
