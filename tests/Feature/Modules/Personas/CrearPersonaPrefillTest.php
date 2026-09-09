<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Personas;

use App\Modules\Personas\Infrastructure\Http\Livewire\CrearPersona;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * La bandeja enlaza a «crear persona» con la identificación y el tipo que
 * trajo la llamada; el formulario debe llegar ya rellenado.
 */
final class CrearPersonaPrefillTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_prellena_identificacion_y_tipo_desde_la_url(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearGestor($proyecto));
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');

        Livewire::withQueryParams(['identificacion' => ' 0102030405 ', 'tipo' => 'ced'])
            ->test(CrearPersona::class)
            ->assertSet('identificacion', '0102030405')
            ->assertSet('tipoIdentificacionId', $tipoCed);
    }

    public function test_tipo_desconocido_deja_el_selector_vacio(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearGestor($proyecto));

        Livewire::withQueryParams(['identificacion' => '0102030405', 'tipo' => 'NOEXISTE'])
            ->test(CrearPersona::class)
            ->assertSet('identificacion', '0102030405')
            ->assertSet('tipoIdentificacionId', null);
    }

    public function test_sin_parametros_el_formulario_arranca_vacio(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearGestor($proyecto));

        Livewire::test(CrearPersona::class)
            ->assertSet('identificacion', '')
            ->assertSet('tipoIdentificacionId', null);
    }

    public function test_guardar_con_los_datos_prellenados_crea_la_persona(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearGestor($proyecto));

        Livewire::withQueryParams(['identificacion' => '0102030405', 'tipo' => 'CED'])
            ->test(CrearPersona::class)
            ->set('nombres', 'Desde')
            ->set('apellidos', 'La Llamada')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('personas', [
            'proyecto_id' => $proyecto->id,
            'identificacion' => '0102030405',
            'nombres' => 'Desde',
        ]);
    }
}
