<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Asignaciones;

use App\Modules\Asignaciones\Application\UseCases\AsignarCasosAEquipo;
use App\Modules\Asignaciones\Infrastructure\Http\Livewire\AsignarMasivamente;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use RuntimeException;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class AsignarCasosAEquipoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_distribuye_casos_round_robin_entre_miembros(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->crearCasosCobranza($proyecto, 5);

        $campanaId = $this->crearCampana((int) $proyecto->id, 'CAMP_MASIVA');

        $g1 = $this->crearGestor($proyecto);
        $g2 = $this->crearGestor($proyecto);
        $g3 = $this->crearGestor($proyecto);

        $equipoId = $this->crearEquipoConMiembros((int) $proyecto->id, 'EQ_DIST', [$g1->id, $g2->id, $g3->id]);

        $casoIds = DB::table('casos')
            ->where('proyecto_id', $proyecto->id)
            ->where('tipo_caso', 'cobranza')
            ->whereNull('cerrado_en')
            ->orderBy('id')
            ->pluck('id')->all();

        $this->assertSame(5, count($casoIds));

        $r = app(AsignarCasosAEquipo::class)->execute(
            proyectoId: (int) $proyecto->id,
            campanaId: $campanaId,
            equipoId: $equipoId,
            limite: 0,
        );

        $this->assertSame(5, $r->asignadas);
        $this->assertSame(0, $r->omitidas);
        // Round-robin con 3 miembros y 5 casos → 2, 2, 1.
        $this->assertSame(2, $r->distribucion[$g1->id]);
        $this->assertSame(2, $r->distribucion[$g2->id]);
        $this->assertSame(1, $r->distribucion[$g3->id]);

        foreach ($casoIds as $caso) {
            $this->assertDatabaseHas('asignaciones', [
                'campana_id' => $campanaId,
                'caso_id' => $caso,
                'estado' => 'pendiente',
            ]);
        }
    }

    public function test_idempotente_no_duplica_ni_reasigna(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->crearCasosCobranza($proyecto, 5);

        $campanaId = $this->crearCampana((int) $proyecto->id, 'CAMP_IDEMP');
        $gestor = $this->crearGestor($proyecto);
        $equipoId = $this->crearEquipoConMiembros((int) $proyecto->id, 'EQ_IDEMP', [$gestor->id]);

        $r1 = app(AsignarCasosAEquipo::class)->execute((int) $proyecto->id, $campanaId, $equipoId, 5);
        $r2 = app(AsignarCasosAEquipo::class)->execute((int) $proyecto->id, $campanaId, $equipoId, 5);

        $this->assertGreaterThan(0, $r1->asignadas);
        $this->assertSame(0, $r2->asignadas);
        $this->assertSame(0, $r2->omitidas, 'Segunda corrida no debería ver casos elegibles');
    }

    public function test_falla_si_equipo_sin_miembros(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $campanaId = $this->crearCampana((int) $proyecto->id, 'CAMP_NOMIEM');
        $equipoId = (int) DB::table('equipos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'codigo' => 'EQ_VACIO',
            'nombre' => 'Vacío',
            'activo' => true,
        ]);

        $this->expectException(RuntimeException::class);
        app(AsignarCasosAEquipo::class)->execute((int) $proyecto->id, $campanaId, $equipoId, 0);
    }

    public function test_falla_si_campana_pertenece_a_otro_proyecto(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCx();
        $campanaB = $this->crearCampana((int) $proyectoB->id, 'CAMP_CX');

        $gestor = $this->crearGestor($proyectoA);
        $equipoA = $this->crearEquipoConMiembros((int) $proyectoA->id, 'EQ_A', [$gestor->id]);

        $this->expectException(RuntimeException::class);
        app(AsignarCasosAEquipo::class)->execute((int) $proyectoA->id, $campanaB, $equipoA, 0);
    }

    public function test_supervisor_accede_ruta_masiva(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);

        $this->actingAs($supervisor)
            ->get(route('proyectos.asignaciones.masiva', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(200);
    }

    public function test_gestor_403_en_ruta_masiva(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        $this->actingAs($gestor)
            ->get(route('proyectos.asignaciones.masiva', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(403);
    }

    public function test_livewire_asignar_dispara_use_case(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->crearCasosCobranza($proyecto, 5);

        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        $campanaId = $this->crearCampana((int) $proyecto->id, 'CAMP_LW');
        $gestor = $this->crearGestor($proyecto);
        $equipoId = $this->crearEquipoConMiembros((int) $proyecto->id, 'EQ_LW', [$gestor->id]);

        Livewire::test(AsignarMasivamente::class)
            ->set('campanaId', $campanaId)
            ->set('equipoId', $equipoId)
            ->set('limite', 2)
            ->call('asignar')
            ->assertHasNoErrors();

        $this->assertSame(2, (int) DB::table('asignaciones')
            ->where('campana_id', $campanaId)->count());
    }

    /**
     * Los casos elegibles que antes traía el seeder demo: mismo proyecto, misma
     * cartera y mismo estado, uno por persona.
     */
    private function crearCasosCobranza(stdClass $proyecto, int $cuantos): void
    {
        $cartera = $this->crearCarteraEn($proyecto, 'CONSUMO');
        $estado = $this->crearEstadoCasoEn($proyecto, 'ABIERTO');

        for ($i = 0; $i < $cuantos; $i++) {
            $this->crearCasoEn($proyecto, [
                'cartera' => $cartera,
                'estado' => $estado,
                'persona' => $this->crearPersonaEn($proyecto),
            ]);
        }
    }

    private function crearCampana(int $proyectoId, string $codigo): int
    {
        return (int) DB::table('campanas')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'codigo' => $codigo,
            'nombre' => $codigo,
            'fecha_inicio' => Carbon::today()->toDateString(),
            'estado' => 'activa',
        ]);
    }

    /** @param list<int> $miembroIds */
    private function crearEquipoConMiembros(int $proyectoId, string $codigo, array $miembroIds): int
    {
        $equipoId = (int) DB::table('equipos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'codigo' => $codigo,
            'nombre' => $codigo,
            'activo' => true,
        ]);
        foreach ($miembroIds as $uid) {
            DB::table('equipo_usuario')->insert([
                'equipo_id' => $equipoId,
                'usuario_id' => $uid,
                'proyecto_id' => $proyectoId,
                'activo' => true,
                'creada_en' => Carbon::now(),
            ]);
        }

        return $equipoId;
    }
}
