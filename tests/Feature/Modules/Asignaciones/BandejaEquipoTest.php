<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Asignaciones;

use App\Modules\Asignaciones\Infrastructure\Http\Livewire\BandejaEquipo;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class BandejaEquipoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_accede_ruta_bandeja_equipo(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);

        $this->actingAs($supervisor)
            ->get(route('proyectos.bandeja.equipo', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(200);
    }

    public function test_gestor_recibe_403_en_bandeja_equipo(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        $this->actingAs($gestor)
            ->get(route('proyectos.bandeja.equipo', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(403);
    }

    public function test_sin_equipo_seleccionado_no_hay_asignaciones(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        $c = Livewire::test(BandejaEquipo::class);
        $asign = $c->viewData('asignaciones');
        $this->assertTrue($asign->isEmpty() || (method_exists($asign, 'total') && $asign->total() === 0));
    }

    public function test_muestra_asignaciones_de_miembros_del_equipo(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);

        $supervisor = $this->crearSupervisor($proyecto);
        $g1 = $this->crearGestor($proyecto);
        $g2 = $this->crearGestor($proyecto);
        $gFuera = $this->crearGestor($proyecto);

        $equipoId = $this->crearEquipoConMiembros($proyecto, 'EQ_BE', [$g1->id, $g2->id]);

        $cartera = $this->crearCarteraEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto);
        $casoIds = [
            $this->crearCasoEn($proyecto, ['cartera' => $cartera, 'estado' => $estado]),
            $this->crearCasoEn($proyecto, ['cartera' => $cartera, 'estado' => $estado]),
            $this->crearCasoEn($proyecto, ['cartera' => $cartera, 'estado' => $estado]),
        ];

        // 2 asignaciones del equipo + 1 a gestor fuera del equipo
        $this->asignar($proyecto, $casoIds[0], (int) $g1->id);
        $this->asignar($proyecto, $casoIds[1], (int) $g2->id);
        $this->asignar($proyecto, $casoIds[2], (int) $gFuera->id);

        $this->actingAs($supervisor);

        $c = Livewire::test(BandejaEquipo::class)
            ->set('equipoId', $equipoId)
            ->set('estadoFiltro', 'todos');

        $asign = $c->viewData('asignaciones');
        $this->assertSame(2, $asign->total());
    }

    public function test_filtro_por_miembro_limita_resultados(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);

        $supervisor = $this->crearSupervisor($proyecto);
        $g1 = $this->crearGestor($proyecto);
        $g2 = $this->crearGestor($proyecto);
        $equipoId = $this->crearEquipoConMiembros($proyecto, 'EQ_F', [$g1->id, $g2->id]);

        $cartera = $this->crearCarteraEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto);
        $casoIds = [
            $this->crearCasoEn($proyecto, ['cartera' => $cartera, 'estado' => $estado]),
            $this->crearCasoEn($proyecto, ['cartera' => $cartera, 'estado' => $estado]),
            $this->crearCasoEn($proyecto, ['cartera' => $cartera, 'estado' => $estado]),
        ];

        $this->asignar($proyecto, $casoIds[0], (int) $g1->id);
        $this->asignar($proyecto, $casoIds[1], (int) $g1->id);
        $this->asignar($proyecto, $casoIds[2], (int) $g2->id);

        $this->actingAs($supervisor);

        $c = Livewire::test(BandejaEquipo::class)
            ->set('equipoId', $equipoId)
            ->set('estadoFiltro', 'todos')
            ->set('miembroId', $g1->id);

        $this->assertSame(2, $c->viewData('asignaciones')->total());
    }

    public function test_no_muestra_asignaciones_de_otro_proyecto(): void
    {
        $proyA = $this->crearProyectoCobranza();
        $proyB = $this->crearProyectoCx();

        $this->activarProyecto($proyA);
        $supervisor = $this->crearSupervisor($proyA);
        $gestor = $this->crearGestor($proyA);
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $gestor->id,
            'proyecto_id' => $proyB->id,
            'rol_id' => (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id'),
            'activo' => true,
        ]);

        $equipoA = $this->crearEquipoConMiembros($proyA, 'EQ_X', [$gestor->id]);

        // Asignación en proyecto B al mismo gestor — NO debe aparecer en bandeja de A.
        $casoB = $this->crearCasoEn($proyB);
        $this->asignar($proyB, $casoB, (int) $gestor->id);

        $this->actingAs($supervisor);
        $c = Livewire::test(BandejaEquipo::class)
            ->set('equipoId', $equipoA)
            ->set('estadoFiltro', 'todos');

        $this->assertSame(0, $c->viewData('asignaciones')->total());
    }

    public function test_supervisor_cambia_prioridad_de_asignacion(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $supervisor = $this->crearSupervisor($proyecto);
        $g1 = $this->crearGestor($proyecto);
        $equipoId = $this->crearEquipoConMiembros($proyecto, 'EQ_PRIO', [$g1->id]);
        $casoId = $this->crearCasoEn($proyecto);
        $this->asignar($proyecto, $casoId, (int) $g1->id);

        $asignacionId = (int) DB::table('asignaciones')
            ->where('proyecto_id', $proyecto->id)
            ->where('caso_id', $casoId)
            ->value('id');

        $this->actingAs($supervisor);
        Livewire::test(BandejaEquipo::class)
            ->set('equipoId', $equipoId)
            ->call('cambiarPrioridad', $asignacionId, 5);

        $this->assertSame(5, (int) DB::table('asignaciones')->where('id', $asignacionId)->value('prioridad'));

        // Clamp: prioridad fuera de rango se ajusta a [0, 9].
        Livewire::test(BandejaEquipo::class)
            ->set('equipoId', $equipoId)
            ->call('cambiarPrioridad', $asignacionId, 99);
        $this->assertSame(9, (int) DB::table('asignaciones')->where('id', $asignacionId)->value('prioridad'));

        Livewire::test(BandejaEquipo::class)
            ->set('equipoId', $equipoId)
            ->call('cambiarPrioridad', $asignacionId, -3);
        $this->assertSame(0, (int) DB::table('asignaciones')->where('id', $asignacionId)->value('prioridad'));
    }

    /** @param list<int> $miembroIds */
    private function crearEquipoConMiembros(stdClass $proyecto, string $codigo, array $miembroIds): int
    {
        $equipoId = (int) DB::table('equipos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'codigo' => $codigo,
            'nombre' => $codigo,
            'activo' => true,
        ]);
        foreach ($miembroIds as $uid) {
            DB::table('equipo_usuario')->insert([
                'equipo_id' => $equipoId,
                'usuario_id' => $uid,
                'proyecto_id' => $proyecto->id,
                'activo' => true,
                'creada_en' => Carbon::now(),
            ]);
        }

        return $equipoId;
    }

    private function asignar(stdClass $proyecto, int $casoId, int $usuarioId): void
    {
        DB::table('asignaciones')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'caso_id' => $casoId,
            'usuario_id' => $usuarioId,
            'fecha_asignacion' => Carbon::today()->toDateString(),
            'prioridad' => 100,
            'estado' => 'pendiente',
        ]);
    }
}
