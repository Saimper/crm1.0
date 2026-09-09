<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Notificaciones;

use App\Models\User;
use App\Modules\Notificaciones\Application\Services\GeneradorNotificaciones;
use App\Modules\Notificaciones\Infrastructure\Http\Livewire\BadgeNotificaciones;
use App\Modules\Notificaciones\Infrastructure\Http\Livewire\ListadoNotificaciones;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class NotificacionesTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_generador_crea_notificaciones_por_vencer_y_vencidos(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearUsuarioConRol($proyecto, 'GESTOR');
        $casoId = $this->crearCasoEn($proyecto);

        $porVencerId = $this->crearCompromiso($proyecto, $casoId, (int) $gestor->id, Carbon::now()->addDay()->toDateString());
        $vencidoId = $this->crearCompromiso($proyecto, $casoId, (int) $gestor->id, Carbon::now()->subDay()->toDateString());
        $lejanoId = $this->crearCompromiso($proyecto, $casoId, (int) $gestor->id, Carbon::now()->addDays(30)->toDateString());

        $creadas = app(GeneradorNotificaciones::class)->ejecutar(umbralDias: 3);

        $this->assertSame(2, $creadas);
        $this->assertDatabaseHas('notificaciones', [
            'proyecto_id' => $proyecto->id,
            'destinatario_usuario_id' => $gestor->id,
            'tipo' => 'compromiso_por_vencer',
            'entidad_id' => $porVencerId,
        ]);
        $this->assertDatabaseHas('notificaciones', [
            'proyecto_id' => $proyecto->id,
            'destinatario_usuario_id' => $gestor->id,
            'tipo' => 'compromiso_vencido',
            'entidad_id' => $vencidoId,
        ]);
        $this->assertDatabaseMissing('notificaciones', [
            'entidad_id' => $lejanoId,
        ]);
    }

    public function test_generador_es_idempotente(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearUsuarioConRol($proyecto, 'GESTOR');
        $casoId = $this->crearCasoEn($proyecto);
        $this->crearCompromiso($proyecto, $casoId, (int) $gestor->id, Carbon::now()->subDay()->toDateString());

        app(GeneradorNotificaciones::class)->ejecutar(umbralDias: 3);
        app(GeneradorNotificaciones::class)->ejecutar(umbralDias: 3);
        app(GeneradorNotificaciones::class)->ejecutar(umbralDias: 3);

        $this->assertSame(1, (int) DB::table('notificaciones')->count());
    }

    public function test_listado_muestra_notificaciones_del_usuario_en_proyecto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);

        $gestor = $this->crearUsuarioConRol($proyecto, 'GESTOR');
        $this->actingAs($gestor);

        $casoId = $this->crearCasoEn($proyecto);
        $this->crearCompromiso($proyecto, $casoId, (int) $gestor->id, Carbon::now()->subDay()->toDateString());
        app(GeneradorNotificaciones::class)->ejecutar();

        $c = Livewire::test(ListadoNotificaciones::class);
        $this->assertSame(1, $c->viewData('notificaciones')->total());
        $this->assertSame(1, $c->viewData('totalNoLeidas'));
    }

    public function test_marcar_leida_actualiza_contador(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);

        $gestor = $this->crearUsuarioConRol($proyecto, 'GESTOR');
        $this->actingAs($gestor);

        $casoId = $this->crearCasoEn($proyecto);
        $this->crearCompromiso($proyecto, $casoId, (int) $gestor->id, Carbon::now()->subDay()->toDateString());
        app(GeneradorNotificaciones::class)->ejecutar();

        $id = (int) DB::table('notificaciones')->first()->id;

        Livewire::test(ListadoNotificaciones::class)
            ->call('marcarLeida', $id);

        $this->assertNotNull(DB::table('notificaciones')->where('id', $id)->value('leida_en'));
    }

    public function test_notificaciones_no_se_comparten_entre_proyectos(): void
    {
        $proyA = $this->crearProyectoCobranza();
        $proyB = $this->crearProyectoCx();

        $gestor = $this->crearUsuarioConRol($proyA, 'GESTOR');
        $this->crearUsuarioProyectoRol((int) $gestor->id, $proyB, 'GESTOR');

        $casoA = $this->crearCasoEn($proyA);
        $this->crearCompromiso($proyA, $casoA, (int) $gestor->id, Carbon::now()->subDay()->toDateString());
        app(GeneradorNotificaciones::class)->ejecutar();

        $this->actingAs($gestor);
        $this->activarProyecto($proyB);

        $c = Livewire::test(ListadoNotificaciones::class);
        $this->assertSame(0, $c->viewData('notificaciones')->total());

        Livewire::test(BadgeNotificaciones::class)->assertSet('noLeidas', 0);
    }

    public function test_ruta_403_sin_permiso_notificaciones_ver(): void
    {
        $proyecto = $this->crearProyectoCobranza();

        $u = User::query()->create([
            'name' => 'Sin', 'email' => 'sin.'.Str::random(4).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);
        // No se asigna rol: no tiene notificaciones.ver.

        $this->actingAs($u)
            ->get(route('proyectos.notificaciones', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(403);
    }

    private function crearCompromiso(stdClass $proyecto, int $casoId, int $usuarioId, string $fechaVenc): int
    {
        return (int) DB::table('compromisos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'caso_id' => $casoId,
            'gestion_origen_id' => null,
            'tipo_compromiso' => 'promesa_pago',
            'estado' => 'pendiente',
            'fecha_vencimiento' => $fechaVenc,
            'usuario_id' => $usuarioId,
        ]);
    }

    /** Asigna un usuario ya existente a otro proyecto: el trait sólo sabe crearlo junto con su rol. */
    private function crearUsuarioProyectoRol(int $usuarioId, stdClass $proyecto, string $codigoRol): void
    {
        $rolId = (int) DB::table('roles')->where('codigo', $codigoRol)->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $usuarioId,
            'proyecto_id' => $proyecto->id,
            'rol_id' => $rolId,
            'activo' => true,
        ]);
    }
}
