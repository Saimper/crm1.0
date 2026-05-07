<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Asignaciones;

use App\Models\User;
use App\Modules\Asignaciones\Infrastructure\Http\Livewire\BandejaEquipo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class BandejaEquipoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

    }

    public function test_supervisor_accede_ruta_bandeja_equipo(): void
    {
        $proyectoId = $this->proyectoId();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');

        $this->actingAs($supervisor)
            ->get(route('proyectos.bandeja.equipo', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);
    }

    public function test_gestor_recibe_403_en_bandeja_equipo(): void
    {
        $proyectoId = $this->proyectoId();
        $gestor = $this->crearConRol($proyectoId, 'GESTOR');

        $this->actingAs($gestor)
            ->get(route('proyectos.bandeja.equipo', ['proyecto_id' => $proyectoId]))
            ->assertStatus(403);
    }

    public function test_sin_equipo_seleccionado_no_hay_asignaciones(): void
    {
        $proyectoId = $this->proyectoId();
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($this->crearConRol($proyectoId, 'SUPERVISOR'));

        $c = Livewire::test(BandejaEquipo::class);
        $asign = $c->viewData('asignaciones');
        $this->assertTrue($asign->isEmpty() || (method_exists($asign, 'total') && $asign->total() === 0));
    }

    public function test_muestra_asignaciones_de_miembros_del_equipo(): void
    {
        $proyectoId = $this->proyectoId();
        $this->bindProyectoActivo($proyectoId);

        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $g1 = $this->crearConRol($proyectoId, 'GESTOR');
        $g2 = $this->crearConRol($proyectoId, 'GESTOR');
        $gFuera = $this->crearConRol($proyectoId, 'GESTOR');

        $equipoId = $this->crearEquipoConMiembros($proyectoId, 'EQ_BE', [$g1->id, $g2->id]);
        $campanaId = $this->crearCampana($proyectoId, 'CAMP_BE');

        $casoIds = DB::table('casos')
            ->where('proyecto_id', $proyectoId)
            ->where('tipo_caso', 'cobranza')
            ->orderBy('id')
            ->pluck('id')->all();

        // 2 asignaciones del equipo + 1 a gestor fuera del equipo
        $this->asignar($proyectoId, $campanaId, $casoIds[0], $g1->id);
        $this->asignar($proyectoId, $campanaId, $casoIds[1], $g2->id);
        $this->asignar($proyectoId, $campanaId, $casoIds[2], $gFuera->id);

        $this->actingAs($supervisor);

        $c = Livewire::test(BandejaEquipo::class)
            ->set('equipoId', $equipoId)
            ->set('estadoFiltro', 'todos');

        $asign = $c->viewData('asignaciones');
        $this->assertSame(2, $asign->total());
    }

    public function test_filtro_por_miembro_limita_resultados(): void
    {
        $proyectoId = $this->proyectoId();
        $this->bindProyectoActivo($proyectoId);

        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $g1 = $this->crearConRol($proyectoId, 'GESTOR');
        $g2 = $this->crearConRol($proyectoId, 'GESTOR');
        $equipoId = $this->crearEquipoConMiembros($proyectoId, 'EQ_F', [$g1->id, $g2->id]);
        $campanaId = $this->crearCampana($proyectoId, 'CAMP_F');

        $casoIds = DB::table('casos')->where('proyecto_id', $proyectoId)->orderBy('id')->pluck('id')->all();
        $this->asignar($proyectoId, $campanaId, $casoIds[0], $g1->id);
        $this->asignar($proyectoId, $campanaId, $casoIds[1], $g1->id);
        $this->asignar($proyectoId, $campanaId, $casoIds[2], $g2->id);

        $this->actingAs($supervisor);

        $c = Livewire::test(BandejaEquipo::class)
            ->set('equipoId', $equipoId)
            ->set('estadoFiltro', 'todos')
            ->set('miembroId', $g1->id);

        $this->assertSame(2, $c->viewData('asignaciones')->total());
    }

    public function test_no_muestra_asignaciones_de_otro_proyecto(): void
    {
        $proyA = $this->proyectoId();
        $proyB = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');

        $this->bindProyectoActivo($proyA);
        $supervisor = $this->crearConRol($proyA, 'SUPERVISOR');
        $gestor = $this->crearConRol($proyA, 'GESTOR');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $gestor->id, 'proyecto_id' => $proyB,
            'rol_id' => (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id'),
            'activo' => true,
        ]);

        $equipoA = $this->crearEquipoConMiembros($proyA, 'EQ_X', [$gestor->id]);

        // Asignación en proyecto B al mismo gestor — NO debe aparecer en bandeja de A.
        $campanaB = $this->crearCampana($proyB, 'CAMP_B');
        $casoB = (int) DB::table('casos')->where('proyecto_id', $proyB)->where('tipo_caso', 'ticket_cx')->value('id');
        $this->asignar($proyB, $campanaB, $casoB, $gestor->id);

        $this->actingAs($supervisor);
        $c = Livewire::test(BandejaEquipo::class)
            ->set('equipoId', $equipoA)
            ->set('estadoFiltro', 'todos');

        $this->assertSame(0, $c->viewData('asignaciones')->total());
    }

    public function test_supervisor_cambia_prioridad_de_asignacion(): void
    {
        $proyectoId = $this->proyectoId();
        $this->bindProyectoActivo($proyectoId);
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $g1 = $this->crearConRol($proyectoId, 'GESTOR');
        $equipoId = $this->crearEquipoConMiembros($proyectoId, 'EQ_PRIO', [$g1->id]);
        $campanaId = $this->crearCampana($proyectoId, 'CAMP_PRIO');
        $casoId = (int) DB::table('casos')->where('proyecto_id', $proyectoId)->value('id');
        $this->asignar($proyectoId, $campanaId, $casoId, $g1->id);

        $asignacionId = (int) DB::table('asignaciones')
            ->where('proyecto_id', $proyectoId)
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

    private function proyectoId(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
    }

    private function bindProyectoActivo(int $proyectoId): void
    {
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));
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

    private function asignar(int $proyectoId, int $campanaId, int $casoId, int $usuarioId): void
    {
        DB::table('asignaciones')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'campana_id' => $campanaId,
            'caso_id' => $casoId,
            'usuario_id' => $usuarioId,
            'fecha_asignacion' => Carbon::today()->toDateString(),
            'prioridad' => 100,
            'estado' => 'pendiente',
        ]);
    }

    private function crearConRol(int $proyectoId, string $codigoRol): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name' => ucfirst(strtolower($codigoRol)),
            'email' => strtolower($codigoRol).'.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);
        $rolId = (int) DB::table('roles')->where('codigo', $codigoRol)->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $u->id, 'proyecto_id' => $proyectoId,
            'rol_id' => $rolId, 'activo' => true,
        ]);

        return $u;
    }
}
