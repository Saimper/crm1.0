<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use App\Models\User;
use App\Modules\Reportes\Infrastructure\Http\Livewire\ConstructorReporte;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class ConstructorLivewireTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    private stdClass $proyecto;

    private int $proyectoId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->proyecto = $this->crearProyectoCobranza();
        $this->proyectoId = (int) $this->proyecto->id;
        $this->activarProyecto($this->proyecto);
    }

    public function test_supervisor_construye_y_guarda_definicion(): void
    {
        $u = $this->usuarioConRol('SUPERVISOR');
        $this->actingAs($u);

        Livewire::test(ConstructorReporte::class)
            ->set('codigo', 'demo_def')
            ->set('nombre', 'Demo')
            ->set('entidadRaiz', 'casos')
            ->call('agregarColumna', 'casos.public_id')
            ->call('agregarColumna', 'casos.tipo_caso')
            ->call('preview')
            ->assertSet('errorGuardar', null)
            ->call('guardar');

        $this->assertDatabaseHas('reportes_definiciones', [
            'codigo' => 'demo_def',
            'proyecto_id' => $this->proyectoId,
        ]);
    }

    public function test_constructor_aborta_para_gestor(): void
    {
        $u = $this->usuarioConRol('GESTOR');
        $this->actingAs($u);

        Livewire::test(ConstructorReporte::class)
            ->assertStatus(403);
    }

    public function test_cambio_entidad_limpia_columnas(): void
    {
        $u = $this->usuarioConRol('SUPERVISOR');
        $this->actingAs($u);

        Livewire::test(ConstructorReporte::class)
            ->call('agregarColumna', 'casos.public_id')
            ->assertCount('columnas', 1)
            ->set('entidadRaiz', 'gestiones')
            ->assertCount('columnas', 0);
    }

    public function test_agregar_filtro_y_quitar(): void
    {
        $u = $this->usuarioConRol('SUPERVISOR');
        $this->actingAs($u);

        Livewire::test(ConstructorReporte::class)
            ->call('agregarFiltro', 'casos.tipo_caso')
            ->assertCount('filtros', 1)
            ->call('quitarFiltro', 0)
            ->assertCount('filtros', 0);
    }

    public function test_campo_invalido_en_agregar_no_rompe(): void
    {
        $u = $this->usuarioConRol('SUPERVISOR');
        $this->actingAs($u);

        Livewire::test(ConstructorReporte::class)
            ->call('agregarColumna', "'; DROP TABLE casos; --")
            ->assertCount('columnas', 0);
    }

    public function test_campos_disponibles_filtra_busqueda(): void
    {
        $u = $this->usuarioConRol('SUPERVISOR');
        $this->actingAs($u);

        $component = Livewire::test(ConstructorReporte::class)
            ->set('busquedaCampo', 'persona');
        $campos = $component->get('camposDisponibles');
        $this->assertNotEmpty($campos);
        foreach (array_keys($campos) as $clave) {
            $this->assertStringContainsString('persona', $clave);
        }
    }

    private function usuarioConRol(string $codigoRol): User
    {
        return $this->crearUsuarioConRol($this->proyecto, $codigoRol);
    }
}
