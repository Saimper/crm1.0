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
use Tests\TestCase;

final class NotificacionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_generador_crea_notificaciones_por_vencer_y_vencidos(): void
    {
        $proyectoId = $this->proyectoCobranzaId();
        $gestor = $this->crearUsuarioConRol($proyectoId, 'GESTOR');

        $casoId = (int) DB::table('casos')
            ->where('proyecto_id', $proyectoId)
            ->where('tipo_caso', 'cobranza')
            ->value('id');

        $porVencerId = $this->crearCompromiso($proyectoId, $casoId, $gestor->id, Carbon::now()->addDay()->toDateString());
        $vencidoId = $this->crearCompromiso($proyectoId, $casoId, $gestor->id, Carbon::now()->subDay()->toDateString());
        $lejanoId = $this->crearCompromiso($proyectoId, $casoId, $gestor->id, Carbon::now()->addDays(30)->toDateString());

        $creadas = app(GeneradorNotificaciones::class)->ejecutar(umbralDias: 3);

        $this->assertSame(2, $creadas);
        $this->assertDatabaseHas('notificaciones', [
            'proyecto_id' => $proyectoId,
            'destinatario_usuario_id' => $gestor->id,
            'tipo'       => 'compromiso_por_vencer',
            'entidad_id' => $porVencerId,
        ]);
        $this->assertDatabaseHas('notificaciones', [
            'proyecto_id' => $proyectoId,
            'destinatario_usuario_id' => $gestor->id,
            'tipo'       => 'compromiso_vencido',
            'entidad_id' => $vencidoId,
        ]);
        $this->assertDatabaseMissing('notificaciones', [
            'entidad_id' => $lejanoId,
        ]);
    }

    public function test_generador_es_idempotente(): void
    {
        $proyectoId = $this->proyectoCobranzaId();
        $gestor = $this->crearUsuarioConRol($proyectoId, 'GESTOR');
        $casoId = (int) DB::table('casos')->where('proyecto_id', $proyectoId)->where('tipo_caso', 'cobranza')->value('id');
        $this->crearCompromiso($proyectoId, $casoId, $gestor->id, Carbon::now()->subDay()->toDateString());

        app(GeneradorNotificaciones::class)->ejecutar(umbralDias: 3);
        app(GeneradorNotificaciones::class)->ejecutar(umbralDias: 3);
        app(GeneradorNotificaciones::class)->ejecutar(umbralDias: 3);

        $this->assertSame(1, (int) DB::table('notificaciones')->count());
    }

    public function test_listado_muestra_notificaciones_del_usuario_en_proyecto(): void
    {
        $proyectoId = $this->proyectoCobranzaId();
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));

        $gestor = $this->crearUsuarioConRol($proyectoId, 'GESTOR');
        $this->actingAs($gestor);

        $casoId = (int) DB::table('casos')->where('proyecto_id', $proyectoId)->where('tipo_caso', 'cobranza')->value('id');
        $this->crearCompromiso($proyectoId, $casoId, $gestor->id, Carbon::now()->subDay()->toDateString());
        app(GeneradorNotificaciones::class)->ejecutar();

        $c = Livewire::test(ListadoNotificaciones::class);
        $this->assertSame(1, $c->viewData('notificaciones')->total());
        $this->assertSame(1, $c->viewData('totalNoLeidas'));
    }

    public function test_marcar_leida_actualiza_contador(): void
    {
        $proyectoId = $this->proyectoCobranzaId();
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));

        $gestor = $this->crearUsuarioConRol($proyectoId, 'GESTOR');
        $this->actingAs($gestor);

        $casoId = (int) DB::table('casos')->where('proyecto_id', $proyectoId)->where('tipo_caso', 'cobranza')->value('id');
        $this->crearCompromiso($proyectoId, $casoId, $gestor->id, Carbon::now()->subDay()->toDateString());
        app(GeneradorNotificaciones::class)->ejecutar();

        $id = (int) DB::table('notificaciones')->first()->id;

        Livewire::test(ListadoNotificaciones::class)
            ->call('marcarLeida', $id);

        $this->assertNotNull(DB::table('notificaciones')->where('id', $id)->value('leida_en'));
    }

    public function test_notificaciones_no_se_comparten_entre_proyectos(): void
    {
        $proyA = $this->proyectoCobranzaId();
        $proyB = $this->proyectoCxId();

        $gestor = $this->crearUsuarioConRol($proyA, 'GESTOR');
        $this->crearUsuarioProyectoRol($gestor->id, $proyB, 'GESTOR');

        $casoA = (int) DB::table('casos')->where('proyecto_id', $proyA)->where('tipo_caso', 'cobranza')->value('id');
        $this->crearCompromiso($proyA, $casoA, $gestor->id, Carbon::now()->subDay()->toDateString());
        app(GeneradorNotificaciones::class)->ejecutar();

        $this->actingAs($gestor);
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyB));

        $c = Livewire::test(ListadoNotificaciones::class);
        $this->assertSame(0, $c->viewData('notificaciones')->total());

        Livewire::test(BadgeNotificaciones::class)->assertSet('noLeidas', 0);
    }

    public function test_ruta_403_sin_permiso_compromisos_ver(): void
    {
        $proyectoId = $this->proyectoCobranzaId();

        $u = User::query()->create([
            'name' => 'Sin', 'email' => 'sin.'.Str::random(4).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);
        // No se asigna rol: no tiene compromisos.ver.

        $this->actingAs($u)
            ->get(route('proyectos.notificaciones', ['proyecto_id' => $proyectoId]))
            ->assertStatus(403);
    }

    private function proyectoCobranzaId(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
    }

    private function proyectoCxId(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
    }

    private function crearCompromiso(int $proyectoId, int $casoId, int $usuarioId, string $fechaVenc): int
    {
        return (int) DB::table('compromisos')->insertGetId([
            'public_id'         => (string) Str::ulid(),
            'proyecto_id'       => $proyectoId,
            'caso_id'           => $casoId,
            'gestion_origen_id' => null,
            'tipo_compromiso'   => 'promesa_pago',
            'estado'            => 'pendiente',
            'fecha_vencimiento' => $fechaVenc,
            'usuario_id'        => $usuarioId,
        ]);
    }

    private function crearUsuarioConRol(int $proyectoId, string $codigoRol): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name'     => ucfirst(strtolower($codigoRol)),
            'email'    => strtolower($codigoRol).'.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'),
            'activo'   => true,
        ]);
        $this->crearUsuarioProyectoRol($u->id, $proyectoId, $codigoRol);

        return $u;
    }

    private function crearUsuarioProyectoRol(int $usuarioId, int $proyectoId, string $codigoRol): void
    {
        $rolId = (int) DB::table('roles')->where('codigo', $codigoRol)->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $usuarioId,
            'proyecto_id' => $proyectoId,
            'rol_id' => $rolId,
            'activo' => true,
        ]);
    }
}
