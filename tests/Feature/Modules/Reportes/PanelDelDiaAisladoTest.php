<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use App\Modules\Reportes\Infrastructure\Http\Livewire\PanelDelDia;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Support\EscenarioMultiMandante;
use Tests\TestCase;

/**
 * El panel mide UN proyecto, y ese proyecto no lo elige el cliente.
 *
 * La primera versión recibía el id como propiedad pública sin bloquear: bastaba
 * con cambiarlo en el payload de /livewire/update para leer las métricas de otra
 * empresa desde una pantalla propia. El middleware valida el proyecto de la URL,
 * no una propiedad del componente.
 */
final class PanelDelDiaAisladoTest extends TestCase
{
    use EscenarioMultiMandante;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_el_id_del_proyecto_no_se_puede_fijar_desde_el_cliente(): void
    {
        $m = $this->montarDosMandantes();
        app()->instance('tenancy.proyecto_activo', (object) ['id' => (int) $m['a']['proyecto']->id]);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($m['a']['gestor'])
            ->test(PanelDelDia::class, ['proyectoId' => (int) $m['a']['proyecto']->id])
            ->set('proyectoId', (int) $m['b']['proyecto']->id);
    }

    public function test_no_se_puede_montar_sobre_un_proyecto_distinto_del_activo(): void
    {
        $m = $this->montarDosMandantes();
        app()->instance('tenancy.proyecto_activo', (object) ['id' => (int) $m['a']['proyecto']->id]);

        Livewire::actingAs($m['a']['gestor'])
            ->test(PanelDelDia::class, ['proyectoId' => (int) $m['b']['proyecto']->id])
            ->assertStatus(403);
    }

    public function test_sin_proyecto_activo_no_se_monta(): void
    {
        $m = $this->montarDosMandantes();

        Livewire::actingAs($m['a']['gestor'])
            ->test(PanelDelDia::class, ['proyectoId' => (int) $m['a']['proyecto']->id])
            ->assertStatus(403);
    }

    public function test_sobre_el_proyecto_activo_funciona(): void
    {
        $m = $this->montarDosMandantes();
        app()->instance('tenancy.proyecto_activo', (object) ['id' => (int) $m['a']['proyecto']->id]);

        Livewire::actingAs($m['a']['gestor'])
            ->test(PanelDelDia::class, ['proyectoId' => (int) $m['a']['proyecto']->id])
            ->assertOk();
    }
}
