<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Usuarios;

use App\Modules\Usuarios\Infrastructure\Http\Livewire\GestionUsuariosProyecto;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Buscar por correo traia a CUALQUIER usuario del CRM y permitia asignarlo al
 * proyecto activo. Ese pivot es justo lo que el SSO usa como prueba de
 * identidad, asi que bastaba conocer un correo ajeno para que el token del
 * propio tenant pasara a resolver a esa persona.
 */
final class BusquedaUsuarioAcotadaAlMandanteTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_no_se_puede_traer_por_correo_a_un_usuario_de_otro_mandante(): void
    {
        $mandanteA = $this->crearMandante();
        $mandanteB = $this->crearMandante();
        $proyectoA = $this->crearProyectoCobranza($mandanteA);
        $proyectoB = $this->crearProyectoCobranza($mandanteB);

        $supervisorA = $this->crearSupervisor($proyectoA);
        $ajeno = $this->crearGestor($proyectoB);

        $this->actingAs($supervisorA);
        app()->instance('tenancy.proyecto_activo', $proyectoA);

        Livewire::test(GestionUsuariosProyecto::class)
            ->set('buscarEmail', (string) $ajeno->email)
            ->call('buscarUsuario')
            ->assertHasErrors('buscarEmail')
            ->assertSet('usuarioBuscadoId', null);
    }

    public function test_un_usuario_del_propio_mandante_si_se_encuentra(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $otroProyecto = $this->crearProyectoCobranza($mandante);

        $supervisor = $this->crearSupervisor($proyecto);
        $companiero = $this->crearGestor($otroProyecto);

        $this->actingAs($supervisor);
        app()->instance('tenancy.proyecto_activo', $proyecto);

        Livewire::test(GestionUsuariosProyecto::class)
            ->set('buscarEmail', (string) $companiero->email)
            ->call('buscarUsuario')
            ->assertHasNoErrors()
            ->assertSet('usuarioBuscadoId', (int) $companiero->id);
    }

    /**
     * Una cuenta creada a mano todavia no pertenece a nadie: asignarla es el
     * alta normal y debe seguir funcionando. No hay escalada, porque el rol que
     * recibe es el del proyecto de quien la asigna.
     */
    public function test_una_cuenta_sin_mandante_si_se_puede_dar_de_alta(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $supervisor = $this->crearSupervisor($proyecto);

        $reciente = \App\Models\User::query()->create([
            'name' => 'Alta Manual',
            'email' => 'suelto@crm.local',
            'password' => bcrypt('x'),
            'activo' => true,
        ]);

        $this->actingAs($supervisor);
        app()->instance('tenancy.proyecto_activo', $proyecto);

        Livewire::test(GestionUsuariosProyecto::class)
            ->set('buscarEmail', (string) $reciente->email)
            ->call('buscarUsuario')
            ->assertHasNoErrors()
            ->assertSet('usuarioBuscadoId', (int) $reciente->id);
    }
}
