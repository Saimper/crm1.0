<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use App\Models\User;
use App\Modules\Reportes\Infrastructure\Http\Livewire\ReporteEquipos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class ReporteEquiposTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

    }

    public function test_supervisor_accede_ruta_reporte_equipos(): void
    {
        $proyectoId = $this->proyectoId();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');

        $this->actingAs($supervisor)
            ->get(route('proyectos.reportes.equipos', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);
    }

    public function test_gestor_recibe_403_reporte_equipos(): void
    {
        $proyectoId = $this->proyectoId();
        $gestor = $this->crearConRol($proyectoId, 'GESTOR');

        $this->actingAs($gestor)
            ->get(route('proyectos.reportes.equipos', ['proyecto_id' => $proyectoId]))
            ->assertStatus(403);
    }

    public function test_agrega_gestiones_por_miembros_del_equipo(): void
    {
        $proyectoId = $this->proyectoId();
        $this->bindProyectoActivo($proyectoId);

        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $gestor1 = $this->crearConRol($proyectoId, 'GESTOR');
        $gestor2 = $this->crearConRol($proyectoId, 'GESTOR');
        $gestorFuera = $this->crearConRol($proyectoId, 'GESTOR');

        $equipoId = $this->crearEquipo($proyectoId, 'EQ_A', 'Equipo A');
        $this->agregarMiembro($equipoId, $gestor1->id, $proyectoId);
        $this->agregarMiembro($equipoId, $gestor2->id, $proyectoId);

        $casoId = (int) DB::table('casos')->where('proyecto_id', $proyectoId)->where('tipo_caso', 'cobranza')->value('id');
        $personaId = (int) DB::table('casos')->where('id', $casoId)->value('persona_id');

        $this->crearGestion($proyectoId, $casoId, $personaId, $gestor1->id);
        $this->crearGestion($proyectoId, $casoId, $personaId, $gestor2->id);
        $this->crearGestion($proyectoId, $casoId, $personaId, $gestorFuera->id);

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
        $proyectoId = $this->proyectoId();
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($this->crearConRol($proyectoId, 'SUPERVISOR'));

        $equipoId = $this->crearEquipo($proyectoId, 'EQ_VACIO', 'Equipo vacío');

        $filas = Livewire::test(ReporteEquipos::class)->viewData('filas');
        $fila = collect($filas)->firstWhere('equipo.id', $equipoId);
        $this->assertNotNull($fila);
        $this->assertSame(0, $fila['miembros_count']);
        $this->assertSame(0, $fila['total_gestiones']);
    }

    public function test_expandir_devuelve_detalle_por_miembro(): void
    {
        $proyectoId = $this->proyectoId();
        $this->bindProyectoActivo($proyectoId);

        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $gestor = $this->crearConRol($proyectoId, 'GESTOR');
        $this->actingAs($supervisor);

        $equipoId = $this->crearEquipo($proyectoId, 'EQ_DETALLE', 'Con detalle');
        $this->agregarMiembro($equipoId, $gestor->id, $proyectoId);

        $casoId = (int) DB::table('casos')->where('proyecto_id', $proyectoId)->where('tipo_caso', 'cobranza')->value('id');
        $personaId = (int) DB::table('casos')->where('id', $casoId)->value('persona_id');
        $this->crearGestion($proyectoId, $casoId, $personaId, $gestor->id);

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
        $proyectoA = $this->proyectoId();
        $proyectoB = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');

        $this->bindProyectoActivo($proyectoA);
        $supervisor = $this->crearConRol($proyectoA, 'SUPERVISOR');
        $gestor = $this->crearConRol($proyectoA, 'GESTOR');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $gestor->id, 'proyecto_id' => $proyectoB,
            'rol_id' => (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id'),
            'activo' => true,
        ]);

        $equipoId = $this->crearEquipo($proyectoA, 'EQ_X', 'Equipo X');
        $this->agregarMiembro($equipoId, $gestor->id, $proyectoA);

        $casoB = (int) DB::table('casos')->where('proyecto_id', $proyectoB)->where('tipo_caso', 'ticket_cx')->value('id');
        $personaB = (int) DB::table('casos')->where('id', $casoB)->value('persona_id');
        $this->crearGestion($proyectoB, $casoB, $personaB, $gestor->id);

        $this->actingAs($supervisor);
        $filas = Livewire::test(ReporteEquipos::class)->set('rango', 'mes')->viewData('filas');

        $fila = collect($filas)->firstWhere('equipo.id', $equipoId);
        $this->assertSame(0, $fila['total_gestiones']);
    }

    private function proyectoId(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
    }

    private function bindProyectoActivo(int $proyectoId): void
    {
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));
    }

    private function crearEquipo(int $proyectoId, string $codigo, string $nombre): int
    {
        return (int) DB::table('equipos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
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

    private function crearGestion(int $proyectoId, int $casoId, int $personaId, int $usuarioId): void
    {
        $tipoGestionId = (int) DB::table('tipos_gestion')->where('proyecto_id', $proyectoId)->value('id');
        $resultadoId = (int) DB::table('resultados')->where('proyecto_id', $proyectoId)->value('id');
        $canalId = (int) DB::table('canales')->value('id');

        DB::table('gestiones')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'caso_id' => $casoId,
            'persona_id' => $personaId,
            'canal_id' => $canalId,
            'tipo_gestion_id' => $tipoGestionId,
            'resultado_id' => $resultadoId,
            'usuario_id' => $usuarioId,
            'creada_en' => Carbon::now(),
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
