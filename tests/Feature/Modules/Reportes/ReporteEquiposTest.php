<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use App\Modules\Reportes\Infrastructure\Http\Livewire\ReporteEquipos;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class ReporteEquiposTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_accede_ruta_reporte_equipos(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);

        $this->actingAs($supervisor)
            ->get(route('proyectos.reportes.equipos', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(200);
    }

    public function test_gestor_recibe_403_reporte_equipos(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        $this->actingAs($gestor)
            ->get(route('proyectos.reportes.equipos', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(403);
    }

    public function test_agrega_gestiones_por_miembros_del_equipo(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);

        $supervisor = $this->crearSupervisor($proyecto);
        $gestor1 = $this->crearGestor($proyecto);
        $gestor2 = $this->crearGestor($proyecto);
        $gestorFuera = $this->crearGestor($proyecto);

        $equipoId = $this->crearEquipo($proyecto, 'EQ_A', 'Equipo A');
        $this->agregarMiembro($equipoId, $gestor1->id, (int) $proyecto->id);
        $this->agregarMiembro($equipoId, $gestor2->id, (int) $proyecto->id);

        $persona = $this->crearPersonaEn($proyecto);
        $casoId = $this->crearCasoEn($proyecto, ['persona' => $persona]);
        $cascada = $this->crearCascadaGestionEn($proyecto);

        $this->crearGestion($proyecto, $casoId, (int) $persona->id, $gestor1->id, $cascada);
        $this->crearGestion($proyecto, $casoId, (int) $persona->id, $gestor2->id, $cascada);
        $this->crearGestion($proyecto, $casoId, (int) $persona->id, $gestorFuera->id, $cascada);

        $this->actingAs($supervisor);

        $c = Livewire::test(ReporteEquipos::class)->set('rango', 'mes');
        $filas = $c->viewData('filas');

        $fila = collect($filas)->firstWhere('equipo.id', $equipoId);
        $this->assertNotNull($fila);
        $this->assertSame(2, $fila['miembros_count']);
        $this->assertSame(2, $fila['total_gestiones']);
        $this->assertSame(1, $fila['cuentas_intentadas']);
    }

    public function test_equipo_sin_miembros_muestra_ceros(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        $equipoId = $this->crearEquipo($proyecto, 'EQ_VACIO', 'Equipo vacío');

        $filas = Livewire::test(ReporteEquipos::class)->viewData('filas');
        $fila = collect($filas)->firstWhere('equipo.id', $equipoId);
        $this->assertNotNull($fila);
        $this->assertSame(0, $fila['miembros_count']);
        $this->assertSame(0, $fila['total_gestiones']);
    }

    public function test_expandir_devuelve_detalle_por_miembro(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);

        $supervisor = $this->crearSupervisor($proyecto);
        $gestor = $this->crearGestor($proyecto);
        $this->actingAs($supervisor);

        $equipoId = $this->crearEquipo($proyecto, 'EQ_DETALLE', 'Con detalle');
        $this->agregarMiembro($equipoId, $gestor->id, (int) $proyecto->id);

        $persona = $this->crearPersonaEn($proyecto);
        $casoId = $this->crearCasoEn($proyecto, ['persona' => $persona]);
        $cascada = $this->crearCascadaGestionEn($proyecto);
        $this->crearGestion($proyecto, $casoId, (int) $persona->id, $gestor->id, $cascada);

        $c = Livewire::test(ReporteEquipos::class)
            ->set('rango', 'mes')
            ->call('expandir', $equipoId);

        $detalle = $c->viewData('detalle');
        $this->assertNotNull($detalle);
        $this->assertCount(1, $detalle);
        $this->assertSame($gestor->id, $detalle[0]['usuario_id']);
        $this->assertSame(1, $detalle[0]['total']);
    }

    public function test_no_agrega_gestiones_de_otro_proyecto(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCx();

        $this->activarProyecto($proyectoA);
        $supervisor = $this->crearSupervisor($proyectoA);
        $gestor = $this->crearGestor($proyectoA);
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $gestor->id,
            'proyecto_id' => $proyectoB->id,
            'rol_id' => (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id'),
            'activo' => true,
        ]);

        $equipoId = $this->crearEquipo($proyectoA, 'EQ_X', 'Equipo X');
        $this->agregarMiembro($equipoId, $gestor->id, (int) $proyectoA->id);

        $personaB = $this->crearPersonaEn($proyectoB);
        $casoB = $this->crearCasoEn($proyectoB, ['persona' => $personaB]);
        $cascadaB = $this->crearCascadaGestionEn($proyectoB);
        $this->crearGestion($proyectoB, $casoB, (int) $personaB->id, $gestor->id, $cascadaB);

        $this->actingAs($supervisor);
        $filas = Livewire::test(ReporteEquipos::class)->set('rango', 'mes')->viewData('filas');

        $fila = collect($filas)->firstWhere('equipo.id', $equipoId);
        $this->assertSame(0, $fila['total_gestiones']);
    }

    private function crearEquipo(stdClass $proyecto, string $codigo, string $nombre): int
    {
        return (int) DB::table('equipos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'activo' => true,
        ]);
    }

    private function agregarMiembro(int $equipoId, int $usuarioId, int $proyectoId): void
    {
        DB::table('equipo_usuario')->insert([
            'equipo_id' => $equipoId,
            'usuario_id' => $usuarioId,
            'proyecto_id' => $proyectoId,
            'activo' => true,
            'creada_en' => Carbon::now(),
        ]);
    }

    /**
     * @param  array{tipo_gestion_id: int, resultado_id: int, canal_id: int, motivo_no_contacto_id: int, causa_id: int}  $cascada
     */
    private function crearGestion(stdClass $proyecto, int $casoId, int $personaId, int $usuarioId, array $cascada): void
    {
        DB::table('gestiones')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'caso_id' => $casoId,
            'persona_id' => $personaId,
            'canal_id' => $cascada['canal_id'],
            'tipo_gestion_id' => $cascada['tipo_gestion_id'],
            'resultado_id' => $cascada['resultado_id'],
            'usuario_id' => $usuarioId,
            'creada_en' => Carbon::now(),
        ]);
    }
}
