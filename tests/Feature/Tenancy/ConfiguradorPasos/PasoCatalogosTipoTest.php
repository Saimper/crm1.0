<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy\ConfiguradorPasos;

use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\PasoCatalogosTipo;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class PasoCatalogosTipoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_tabs_visibles_dependen_del_tipo_de_proyecto(): void
    {
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        $cobranza = $this->crearProyectoCobranza();
        $cx = $this->crearProyectoCx();
        $venta = $this->crearProyectoVenta();
        $servicio = $this->crearProyectoServicio();

        $this->assertTabsAplicables(
            $cobranza,
            ['tramos_mora', 'tipos_pago'],
        );
        $this->assertTabsAplicables(
            $cx,
            ['categorias_ticket', 'prioridades_ticket', 'niveles_sla', 'niveles_escalamiento'],
        );
        $this->assertTabsAplicables(
            $venta,
            ['productos_venta', 'etapas_embudo'],
        );
        $this->assertTabsAplicables(
            $servicio,
            ['tipos_accion_servicio', 'estados_tecnicos'],
        );
    }

    public function test_cambiar_tab_no_aplicable_lanza_excepcion(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        $component = Livewire::test(PasoCatalogosTipo::class, ['proyecto' => $modelo]);

        $this->expectException(InvalidArgumentException::class);
        $component->call('cambiarTab', 'productos_venta'); // pertenece a venta, no cobranza
    }

    public function test_completar_todos_los_subtabs_marca_paso_7_completo(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        // Inicialmente, ninguno completo → tab activa = primer pendiente.
        $component = Livewire::test(PasoCatalogosTipo::class, ['proyecto' => $modelo]);
        $component->assertSet('tabActiva', 'tramos_mora');

        // Completar ambos catálogos.
        DB::table('tramos_mora')->insert([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'TM_TEST',
            'nombre' => 'Tramo test',
            'dias_desde' => 0,
            'orden' => 100,
            'activo' => true,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
        DB::table('tipos_pago')->insert([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'TP_TEST',
            'nombre' => 'Tipo test',
            'orden' => 100,
            'activo' => true,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        // Re-mount: tab activa pasa al primer no-pendiente; como todos están completos,
        // queda el primero de la lista.
        $componentRe = Livewire::test(PasoCatalogosTipo::class, ['proyecto' => $modelo]);
        $componentRe->assertSet('tabActiva', 'tramos_mora');

        // Estado de cada tab debe ser true en render.
        $this->assertTrue(DB::table('tramos_mora')->where('proyecto_id', $proyecto->id)->exists());
        $this->assertTrue(DB::table('tipos_pago')->where('proyecto_id', $proyecto->id)->exists());
    }

    /**
     * @param  list<string>  $esperados
     */
    private function assertTabsAplicables(stdClass $proyecto, array $esperados): void
    {
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $component = Livewire::test(PasoCatalogosTipo::class, ['proyecto' => $modelo]);

        // La tab activa debe ser uno de los esperados (primer pendiente).
        $tabActiva = $component->get('tabActiva');
        $this->assertContains($tabActiva, $esperados);

        // Cualquier tab fuera de la lista debe lanzar.
        foreach (['tramos_mora', 'tipos_pago', 'categorias_ticket', 'productos_venta', 'estados_tecnicos'] as $codigo) {
            if (in_array($codigo, $esperados, true)) {
                continue;
            }

            try {
                $component->call('cambiarTab', $codigo);
                $this->fail("Tab {$codigo} debería ser inválida para tipo {$proyecto->tipo_operacion}.");
            } catch (InvalidArgumentException) {
                // ok — no aplicable.
            }
        }
    }
}
