<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Modules\Tenancy\Domain\ConfiguracionProyecto\CalculadorAvanceConfiguracion;
use App\Modules\Tenancy\Domain\ConfiguracionProyecto\PasoConfiguracion;
use App\Modules\Tenancy\Domain\ValueObjects\TipoOperacion;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Journey end-to-end del wizard F36 — uno por tipo de operación.
 * Cada test:
 *   1. Crea un proyecto del tipo indicado.
 *   2. Inserta los datos mínimos (1 fila por paso obligatorio + N catálogos
 *      tipo-específicos) — equivalente a recorrer los 9 pasos del wizard.
 *   3. Verifica completitud, smart-link a edición, y conteos por tabla.
 *
 * Usamos inserts directos en vez de Livewire::test paso-a-paso para mantener
 * el test rápido (~1s por journey). Los Livewires individuales ya están
 * cubiertos por los tests específicos de P3–P6.
 */
final class ConfiguradorJourneyCompletoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_journey_completo_proyecto_cobranza(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->insertarObligatoriosComunes($proyecto);
        $this->insertarCatalogosCobranza($proyecto);

        $this->verificarJourneyCompleto($proyecto, TipoOperacion::COBRANZA);
    }

    public function test_journey_completo_proyecto_cx(): void
    {
        $proyecto = $this->crearProyectoCx();
        $this->insertarObligatoriosComunes($proyecto);
        $this->insertarCatalogosCx($proyecto);

        $this->verificarJourneyCompleto($proyecto, TipoOperacion::CX);
    }

    public function test_journey_completo_proyecto_venta(): void
    {
        $proyecto = $this->crearProyectoVenta();
        $this->insertarObligatoriosComunes($proyecto);
        $this->insertarCatalogosVenta($proyecto);

        $this->verificarJourneyCompleto($proyecto, TipoOperacion::VENTA);
    }

    public function test_journey_completo_proyecto_servicio(): void
    {
        $proyecto = $this->crearProyectoServicio();
        $this->insertarObligatoriosComunes($proyecto);
        $this->insertarCatalogosServicio($proyecto);

        $this->verificarJourneyCompleto($proyecto, TipoOperacion::SERVICIO);
    }

    private function verificarJourneyCompleto(stdClass $proyecto, TipoOperacion $tipo): void
    {
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        /** @var CalculadorAvanceConfiguracion $calculador */
        $calculador = app(CalculadorAvanceConfiguracion::class);
        $avance = $calculador->calcular((int) $proyecto->id);

        $this->assertTrue($avance->estaCompleto(), "Avance debe estar completo para tipo {$tipo->value}.");
        $this->assertSame(PasoConfiguracion::RESUMEN, $avance->pasoActual());

        // Smart-link del sidebar apunta a edición.
        $resp = $this->get(route('proyectos.casos.lista', ['proyecto_id' => $proyecto->id]));
        $resp->assertStatus(200);
        $resp->assertSee(
            route('admin.proyectos.configurar.editar', ['proyecto' => $proyecto->public_id]),
            false,
        );

        // El modo edición es accesible.
        $this->get(route('admin.proyectos.configurar.editar', ['proyecto' => $proyecto->public_id]))
            ->assertStatus(200);

        // Conteos: 1 fila en cada catálogo común + N en los tipo-específicos del tipo.
        $proyectoId = (int) $proyecto->id;
        $this->assertSame(1, (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->whereNull('eliminada_en')->count());
        $this->assertSame(1, (int) DB::table('estados_caso')->where('proyecto_id', $proyectoId)->count());
        $this->assertSame(1, (int) DB::table('tipos_gestion')->where('proyecto_id', $proyectoId)->count());
        $this->assertSame(1, (int) DB::table('resultados')->where('proyecto_id', $proyectoId)->count());
        $this->assertSame(1, (int) DB::table('motivos_no_contacto')->where('proyecto_id', $proyectoId)->count());

        foreach (PasoConfiguracion::subPasosCatalogosPorTipo($tipo) as $tabla) {
            $this->assertSame(
                1,
                (int) DB::table($tabla)->where('proyecto_id', $proyectoId)->count(),
                "Catálogo tipo-específico {$tabla} debe tener 1 fila para tipo {$tipo->value}.",
            );
        }
    }

    private function insertarObligatoriosComunes(stdClass $proyecto): void
    {
        $now = Carbon::now();
        $proyectoId = (int) $proyecto->id;

        $this->crearCarteraEn($proyecto);
        $this->crearEstadoCasoEn($proyecto);

        DB::table('tipos_gestion')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'TG', 'nombre' => 'Tipo gestión',
            'orden' => 100, 'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
        DB::table('resultados')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'R', 'nombre' => 'Resultado',
            'orden' => 100, 'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
        DB::table('motivos_no_contacto')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'M', 'nombre' => 'Motivo',
            'orden' => 100, 'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
    }

    private function insertarCatalogosCobranza(stdClass $proyecto): void
    {
        $now = Carbon::now();
        $proyectoId = (int) $proyecto->id;

        DB::table('tramos_mora')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'TM', 'nombre' => 'Tramo',
            'dias_desde' => 0, 'orden' => 100, 'activo' => true,
            'creada_en' => $now, 'actualizada_en' => $now,
        ]);
        DB::table('tipos_pago')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'TP', 'nombre' => 'Tipo pago',
            'orden' => 100, 'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
    }

    private function insertarCatalogosCx(stdClass $proyecto): void
    {
        $now = Carbon::now();
        $proyectoId = (int) $proyecto->id;

        DB::table('categorias_ticket')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'CAT', 'nombre' => 'Categoría',
            'orden' => 100, 'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
        DB::table('prioridades_ticket')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'PRI', 'nombre' => 'Prioridad',
            'peso' => 100, 'orden' => 100, 'activo' => true,
            'creada_en' => $now, 'actualizada_en' => $now,
        ]);
        DB::table('niveles_sla')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'SLA', 'nombre' => 'SLA',
            'horas_resolucion' => 24, 'orden' => 100, 'activo' => true,
            'creada_en' => $now, 'actualizada_en' => $now,
        ]);
        DB::table('niveles_escalamiento')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'ESC', 'nombre' => 'Nivel',
            'nivel' => 1, 'orden' => 100, 'activo' => true,
            'creada_en' => $now, 'actualizada_en' => $now,
        ]);
    }

    private function insertarCatalogosVenta(stdClass $proyecto): void
    {
        $now = Carbon::now();
        $proyectoId = (int) $proyecto->id;

        DB::table('productos_venta')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'PV', 'nombre' => 'Producto',
            'orden' => 100, 'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
        DB::table('etapas_embudo')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'EE', 'nombre' => 'Etapa',
            'nivel' => 1, 'probabilidad_cierre' => 50, 'orden' => 100, 'activo' => true,
            'creada_en' => $now, 'actualizada_en' => $now,
        ]);
    }

    private function insertarCatalogosServicio(stdClass $proyecto): void
    {
        $now = Carbon::now();
        $proyectoId = (int) $proyecto->id;

        DB::table('tipos_accion_servicio')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'TAS', 'nombre' => 'Tipo acción',
            'orden' => 100, 'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
        DB::table('estados_tecnicos')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'ET', 'nombre' => 'Estado técnico',
            'orden' => 100, 'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
    }
}
