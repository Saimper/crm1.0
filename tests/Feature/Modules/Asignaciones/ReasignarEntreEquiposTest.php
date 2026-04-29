<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Asignaciones;

use App\Models\User;
use App\Modules\Asignaciones\Application\UseCases\ReasignarCasosEntreEquipos;
use App\Modules\Asignaciones\Infrastructure\Http\Livewire\ReasignarEntreEquipos;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

final class ReasignarEntreEquiposTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_mueve_asignaciones_pendientes_round_robin(): void
    {
        $proyectoId = $this->proyectoId();
        $campanaId = $this->crearCampana($proyectoId, 'CAMP_RA');

        $gOrigen = $this->crearConRol($proyectoId, 'GESTOR');
        $gDest1 = $this->crearConRol($proyectoId, 'GESTOR');
        $gDest2 = $this->crearConRol($proyectoId, 'GESTOR');

        $eqOrigen = $this->crearEquipoConMiembros($proyectoId, 'EQ_ORI', [$gOrigen->id]);
        $eqDest = $this->crearEquipoConMiembros($proyectoId, 'EQ_DES', [$gDest1->id, $gDest2->id]);

        $casoIds = DB::table('casos')->where('proyecto_id', $proyectoId)->orderBy('id')->pluck('id')->all();
        foreach ($casoIds as $cid) {
            $this->asignar($proyectoId, $campanaId, $cid, $gOrigen->id, 'pendiente');
        }

        $r = app(ReasignarCasosEntreEquipos::class)->execute(
            proyectoId: $proyectoId,
            equipoOrigenId: $eqOrigen,
            equipoDestinoId: $eqDest,
            limite: 0,
        );

        $this->assertSame(count($casoIds), $r->asignadas);
        // Round-robin: 5 → 3/2 entre los 2 miembros destino.
        $this->assertSame(3, $r->distribucion[$gDest1->id]);
        $this->assertSame(2, $r->distribucion[$gDest2->id]);

        // Ninguna asignación de la campaña nueva quedó con gOrigen.
        $this->assertSame(0, (int) DB::table('asignaciones')
            ->where('campana_id', $campanaId)
            ->where('usuario_id', $gOrigen->id)
            ->count());
    }

    public function test_no_mueve_asignaciones_en_trabajo_ni_cerradas(): void
    {
        $proyectoId = $this->proyectoId();
        $campanaId = $this->crearCampana($proyectoId, 'CAMP_ESTADO');
        $gOri = $this->crearConRol($proyectoId, 'GESTOR');
        $gDest = $this->crearConRol($proyectoId, 'GESTOR');
        $eqOri = $this->crearEquipoConMiembros($proyectoId, 'EQ_EST_O', [$gOri->id]);
        $eqDes = $this->crearEquipoConMiembros($proyectoId, 'EQ_EST_D', [$gDest->id]);

        $casoIds = DB::table('casos')->where('proyecto_id', $proyectoId)->orderBy('id')->limit(3)->pluck('id')->all();
        $this->asignar($proyectoId, $campanaId, $casoIds[0], $gOri->id, 'pendiente');
        $this->asignar($proyectoId, $campanaId, $casoIds[1], $gOri->id, 'en_trabajo');
        $this->asignar($proyectoId, $campanaId, $casoIds[2], $gOri->id, 'cerrada');

        $r = app(ReasignarCasosEntreEquipos::class)->execute($proyectoId, $eqOri, $eqDes, 0);
        $this->assertSame(1, $r->asignadas);

        // La pendiente se movió
        $this->assertSame($gDest->id, (int) DB::table('asignaciones')
            ->where('campana_id', $campanaId)->where('caso_id', $casoIds[0])->value('usuario_id'));
        // Las otras dos quedaron con gOri
        $this->assertSame($gOri->id, (int) DB::table('asignaciones')
            ->where('campana_id', $campanaId)->where('caso_id', $casoIds[1])->value('usuario_id'));
        $this->assertSame($gOri->id, (int) DB::table('asignaciones')
            ->where('campana_id', $campanaId)->where('caso_id', $casoIds[2])->value('usuario_id'));
    }

    public function test_falla_si_origen_y_destino_iguales(): void
    {
        $proyectoId = $this->proyectoId();
        $g = $this->crearConRol($proyectoId, 'GESTOR');
        $eq = $this->crearEquipoConMiembros($proyectoId, 'EQ_SOLO', [$g->id]);

        $this->expectException(RuntimeException::class);
        app(ReasignarCasosEntreEquipos::class)->execute($proyectoId, $eq, $eq, 0);
    }

    public function test_falla_si_destino_sin_miembros(): void
    {
        $proyectoId = $this->proyectoId();
        $campanaId = $this->crearCampana($proyectoId, 'CAMP_NODEST');
        $gOri = $this->crearConRol($proyectoId, 'GESTOR');
        $eqOri = $this->crearEquipoConMiembros($proyectoId, 'EQ_NOD_O', [$gOri->id]);
        $eqDes = (int) DB::table('equipos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'codigo' => 'EQ_NOD_D', 'nombre' => 'Vacío',
            'activo' => true,
        ]);
        $casoId = (int) DB::table('casos')->where('proyecto_id', $proyectoId)->value('id');
        $this->asignar($proyectoId, $campanaId, $casoId, $gOri->id, 'pendiente');

        $this->expectException(RuntimeException::class);
        app(ReasignarCasosEntreEquipos::class)->execute($proyectoId, $eqOri, $eqDes, 0);
    }

    public function test_supervisor_accede_ruta_y_gestor_403(): void
    {
        $proyectoId = $this->proyectoId();

        $this->actingAs($this->crearConRol($proyectoId, 'SUPERVISOR'))
            ->get(route('proyectos.asignaciones.reasignar', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);

        $this->actingAs($this->crearConRol($proyectoId, 'GESTOR'))
            ->get(route('proyectos.asignaciones.reasignar', ['proyecto_id' => $proyectoId]))
            ->assertStatus(403);
    }

    public function test_livewire_dispara_use_case(): void
    {
        $proyectoId = $this->proyectoId();
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));
        $this->actingAs($this->crearConRol($proyectoId, 'SUPERVISOR'));

        $campanaId = $this->crearCampana($proyectoId, 'CAMP_LW_RA');
        $gOri = $this->crearConRol($proyectoId, 'GESTOR');
        $gDes = $this->crearConRol($proyectoId, 'GESTOR');
        $eqO = $this->crearEquipoConMiembros($proyectoId, 'EQ_LW_O', [$gOri->id]);
        $eqD = $this->crearEquipoConMiembros($proyectoId, 'EQ_LW_D', [$gDes->id]);

        $casoId = (int) DB::table('casos')->where('proyecto_id', $proyectoId)->value('id');
        $this->asignar($proyectoId, $campanaId, $casoId, $gOri->id, 'pendiente');

        Livewire::test(ReasignarEntreEquipos::class)
            ->set('equipoOrigenId', $eqO)
            ->set('equipoDestinoId', $eqD)
            ->call('reasignar')
            ->assertHasNoErrors();

        $this->assertSame($gDes->id, (int) DB::table('asignaciones')
            ->where('campana_id', $campanaId)
            ->where('caso_id', $casoId)
            ->value('usuario_id'));
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

    private function asignar(int $proyectoId, int $campanaId, int $casoId, int $usuarioId, string $estado): void
    {
        DB::table('asignaciones')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'campana_id' => $campanaId,
            'caso_id' => $casoId,
            'usuario_id' => $usuarioId,
            'fecha_asignacion' => Carbon::today()->toDateString(),
            'prioridad' => 100,
            'estado' => $estado,
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
