<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Asignaciones;

use App\Models\User;
use App\Modules\Asignaciones\Application\UseCases\AsignarCasosAEquipo;
use App\Modules\Asignaciones\Infrastructure\Http\Livewire\AsignarMasivamente;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

final class AsignarCasosAEquipoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_distribuye_casos_round_robin_entre_miembros(): void
    {
        $proyectoId = $this->proyectoId();
        $campanaId = $this->crearCampana($proyectoId, 'CAMP_MASIVA');

        $g1 = $this->crearConRol($proyectoId, 'GESTOR');
        $g2 = $this->crearConRol($proyectoId, 'GESTOR');
        $g3 = $this->crearConRol($proyectoId, 'GESTOR');

        $equipoId = $this->crearEquipoConMiembros($proyectoId, 'EQ_DIST', [$g1->id, $g2->id, $g3->id]);

        $casoIds = DB::table('casos')
            ->where('proyecto_id', $proyectoId)
            ->where('tipo_caso', 'cobranza')
            ->whereNull('cerrado_en')
            ->orderBy('id')
            ->pluck('id')->all();

        $this->assertSame(5, count($casoIds));

        $r = app(AsignarCasosAEquipo::class)->execute(
            proyectoId: $proyectoId,
            campanaId:  $campanaId,
            equipoId:   $equipoId,
            limite:     0,
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
                'caso_id'    => $caso,
                'estado'     => 'pendiente',
            ]);
        }
    }

    public function test_idempotente_no_duplica_ni_reasigna(): void
    {
        $proyectoId = $this->proyectoId();
        $campanaId = $this->crearCampana($proyectoId, 'CAMP_IDEMP');
        $gestor = $this->crearConRol($proyectoId, 'GESTOR');
        $equipoId = $this->crearEquipoConMiembros($proyectoId, 'EQ_IDEMP', [$gestor->id]);

        $r1 = app(AsignarCasosAEquipo::class)->execute($proyectoId, $campanaId, $equipoId, 5);
        $r2 = app(AsignarCasosAEquipo::class)->execute($proyectoId, $campanaId, $equipoId, 5);

        $this->assertGreaterThan(0, $r1->asignadas);
        $this->assertSame(0, $r2->asignadas);
        $this->assertSame(0, $r2->omitidas, 'Segunda corrida no debería ver casos elegibles');
    }

    public function test_falla_si_equipo_sin_miembros(): void
    {
        $proyectoId = $this->proyectoId();
        $campanaId = $this->crearCampana($proyectoId, 'CAMP_NOMIEM');
        $equipoId = (int) DB::table('equipos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'codigo' => 'EQ_VACIO',
            'nombre' => 'Vacío',
            'activo' => true,
        ]);

        $this->expectException(RuntimeException::class);
        app(AsignarCasosAEquipo::class)->execute($proyectoId, $campanaId, $equipoId, 0);
    }

    public function test_falla_si_campana_pertenece_a_otro_proyecto(): void
    {
        $proyectoA = $this->proyectoId();
        $proyectoB = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
        $campanaB = $this->crearCampana($proyectoB, 'CAMP_CX');

        $gestor = $this->crearConRol($proyectoA, 'GESTOR');
        $equipoA = $this->crearEquipoConMiembros($proyectoA, 'EQ_A', [$gestor->id]);

        $this->expectException(RuntimeException::class);
        app(AsignarCasosAEquipo::class)->execute($proyectoA, $campanaB, $equipoA, 0);
    }

    public function test_supervisor_accede_ruta_masiva(): void
    {
        $proyectoId = $this->proyectoId();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');

        $this->actingAs($supervisor)
            ->get(route('proyectos.asignaciones.masiva', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);
    }

    public function test_gestor_403_en_ruta_masiva(): void
    {
        $proyectoId = $this->proyectoId();
        $gestor = $this->crearConRol($proyectoId, 'GESTOR');

        $this->actingAs($gestor)
            ->get(route('proyectos.asignaciones.masiva', ['proyecto_id' => $proyectoId]))
            ->assertStatus(403);
    }

    public function test_livewire_asignar_dispara_use_case(): void
    {
        $proyectoId = $this->proyectoId();
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));
        $this->actingAs($this->crearConRol($proyectoId, 'SUPERVISOR'));

        $campanaId = $this->crearCampana($proyectoId, 'CAMP_LW');
        $gestor = $this->crearConRol($proyectoId, 'GESTOR');
        $equipoId = $this->crearEquipoConMiembros($proyectoId, 'EQ_LW', [$gestor->id]);

        Livewire::test(AsignarMasivamente::class)
            ->set('campanaId', $campanaId)
            ->set('equipoId', $equipoId)
            ->set('limite', 2)
            ->call('asignar')
            ->assertHasNoErrors();

        $this->assertSame(2, (int) DB::table('asignaciones')
            ->where('campana_id', $campanaId)->count());
    }

    private function proyectoId(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
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

    private function crearConRol(int $proyectoId, string $codigoRol): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name'     => ucfirst(strtolower($codigoRol)),
            'email'    => strtolower($codigoRol).'.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'),
            'activo'   => true,
        ]);
        $rolId = (int) DB::table('roles')->where('codigo', $codigoRol)->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $u->id, 'proyecto_id' => $proyectoId,
            'rol_id' => $rolId, 'activo' => true,
        ]);
        return $u;
    }
}
