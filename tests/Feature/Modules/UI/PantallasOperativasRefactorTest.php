<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\UI;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Fase 29: verifica que las pantallas operativas refactorizadas renderizan
 * con el design system F29 (.page + .page-header + .card).
 */
final class PantallasOperativasRefactorTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_bandeja_usa_tokens_design_system(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        $response = $this->actingAs($gestor)
            ->get(route('proyectos.bandeja', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(200);

        // Page header con tokens
        $response->assertSee('page-header', false);
        // Card / shadow del sistema
        $response->assertSee('class="card"', false);
    }

    public function test_bandeja_equipo_refactorizada(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);

        $response = $this->actingAs($supervisor)
            ->get(route('proyectos.bandeja.equipo', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(200);

        $response->assertSee('page-header', false);
        $response->assertSee('class="card"', false);
    }

    public function test_notificaciones_refactorizada(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        $response = $this->actingAs($gestor)
            ->get(route('proyectos.notificaciones', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(200);

        $response->assertSee('page-header', false);
    }

    public function test_vista_trabajo_shell_refactorizada(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        $persona = $this->crearPersonaEn($proyecto);
        $casoId = $this->crearCasoEn($proyecto, ['persona' => $persona]);
        $this->insertarCtiCobranza($proyecto, $casoId);

        $personaPublicId = (string) DB::table('personas')
            ->where('proyecto_id', $proyecto->id)
            ->value('public_id');
        $this->assertNotEmpty($personaPublicId);

        $response = $this->actingAs($gestor)
            ->get(route('proyectos.trabajo', [
                'proyecto_id' => $proyecto->id,
                'persona' => $personaPublicId,
            ]))
            ->assertStatus(200);

        $response->assertSee('Vista de trabajo', false);
        $response->assertSee('page-header', false);
    }

    /**
     * La fila CTI del caso de cobranza. `EscenarioOperativo::crearCasoEn` sólo
     * inserta en `casos`, y la Vista de Trabajo pinta el panel del tipo leyendo
     * `casos_cobranza`; sin esta fila el caso se renderiza a medias.
     */
    private function insertarCtiCobranza(stdClass $proyecto, int $casoId): void
    {
        DB::table('casos_cobranza')->insert([
            'caso_id' => $casoId,
            'proyecto_id' => $proyecto->id,
            'numero_prestamo' => 'PRST-'.Str::random(6),
            'monto_original' => 1000.00,
            'saldo_capital' => 1000.00,
            'saldo_total' => 1000.00,
            'cuota_mensual' => 100.00,
            'cuotas_totales' => 12,
            'fecha_desembolso' => Carbon::today()->subYear(),
            'fecha_vencimiento' => Carbon::today()->addYear(),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
    }
}
