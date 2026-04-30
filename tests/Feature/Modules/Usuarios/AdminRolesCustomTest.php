<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Usuarios;

use App\Models\User;
use App\Modules\Usuarios\Application\RolesCustom\DTOs\EntradaRolCustom;
use App\Modules\Usuarios\Application\RolesCustom\UseCases\CrearRolCustom;
use App\Modules\Usuarios\Domain\RolesCustom\Exceptions\PermisoNoAsignableARolCustom;
use App\Modules\Usuarios\Infrastructure\Http\Livewire\AdminRolesCustom;
use App\Modules\Usuarios\Infrastructure\Http\Livewire\GestionUsuariosProyecto;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 33 — CRUD de roles custom + protecciones.
 */
final class AdminRolesCustomTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_global_crea_rol_custom_con_permisos(): void
    {
        $proyectoId = $this->proyectoVenta();
        $this->loginAdminGlobal($proyectoId);

        Livewire::test(AdminRolesCustom::class)
            ->call('abrirFormCrear')
            ->set('form_codigo', 'GESTOR_TELEVENTAS')
            ->set('form_nombre', 'Gestor de televentas')
            ->set('form_descripcion', 'Combinación específica')
            ->set('form_permisos', ['casos.ver', 'gestiones.crear', 'compromisos.crear', 'compromisos.resolver'])
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('roles_custom', [
            'proyecto_id' => $proyectoId,
            'codigo' => 'GESTOR_TELEVENTAS',
            'nombre' => 'Gestor de televentas',
            'activo' => true,
        ]);

        $rolId = (int) DB::table('roles_custom')
            ->where('proyecto_id', $proyectoId)
            ->where('codigo', 'GESTOR_TELEVENTAS')
            ->value('id');

        $count = (int) DB::table('rol_custom_permiso')->where('rol_custom_id', $rolId)->count();
        $this->assertSame(4, $count);
    }

    public function test_supervisor_recibe_403_en_ruta_admin_roles_custom(): void
    {
        $proyectoId = $this->proyectoVenta();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');

        $this->actingAs($supervisor)
            ->get(route('proyectos.admin.roles-custom', ['proyecto_id' => $proyectoId]))
            ->assertStatus(403);
    }

    public function test_gestor_recibe_403_en_ruta_admin_roles_custom(): void
    {
        $proyectoId = $this->proyectoVenta();
        $gestor = $this->crearConRol($proyectoId, 'GESTOR');

        $this->actingAs($gestor)
            ->get(route('proyectos.admin.roles-custom', ['proyecto_id' => $proyectoId]))
            ->assertStatus(403);
    }

    public function test_admin_global_accede_ruta(): void
    {
        $proyectoId = $this->proyectoVenta();
        $this->loginAdminGlobal($proyectoId);

        $this->get(route('proyectos.admin.roles-custom', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);
    }

    public function test_codigo_duplicado_en_mismo_proyecto_falla(): void
    {
        $proyectoId = $this->proyectoVenta();
        $this->loginAdminGlobal($proyectoId);

        $useCase = $this->app->make(CrearRolCustom::class);
        $useCase->execute(
            new EntradaRolCustom($proyectoId, 'CUSTOM_X', 'X', null, ['casos.ver']),
            $this->adminId(),
        );

        Livewire::test(AdminRolesCustom::class)
            ->call('abrirFormCrear')
            ->set('form_codigo', 'CUSTOM_X')
            ->set('form_nombre', 'Otro X')
            ->set('form_permisos', ['casos.ver'])
            ->call('guardar')
            ->assertHasErrors();
    }

    public function test_payload_con_permiso_definir_es_rechazado(): void
    {
        $proyectoId = $this->proyectoVenta();
        $this->loginAdminGlobal($proyectoId);

        $useCase = $this->app->make(CrearRolCustom::class);

        $this->expectException(PermisoNoAsignableARolCustom::class);
        $useCase->execute(
            new EntradaRolCustom($proyectoId, 'INVALIDO', 'Inválido', null, ['casos.ver', 'campos.definir']),
            $this->adminId(),
        );
    }

    public function test_livewire_filtra_permiso_definir_silenciosamente(): void
    {
        $proyectoId = $this->proyectoVenta();
        $this->loginAdminGlobal($proyectoId);

        // Si el front intenta enviar campos.definir vía payload manipulado,
        // el componente lo filtra antes del UseCase: el rol queda con los demás.
        Livewire::test(AdminRolesCustom::class)
            ->call('abrirFormCrear')
            ->set('form_codigo', 'CUSTOM_FILT')
            ->set('form_nombre', 'Filtrado')
            ->set('form_permisos', ['casos.ver', 'campos.definir', 'gestiones.crear'])
            ->call('guardar')
            ->assertHasNoErrors();

        $rolId = (int) DB::table('roles_custom')
            ->where('proyecto_id', $proyectoId)
            ->where('codigo', 'CUSTOM_FILT')
            ->value('id');

        $codigos = DB::table('rol_custom_permiso as rcp')
            ->join('permisos as p', 'p.id', '=', 'rcp.permiso_id')
            ->where('rcp.rol_custom_id', $rolId)
            ->pluck('p.codigo')
            ->all();

        $this->assertNotContains('campos.definir', $codigos);
        $this->assertContains('casos.ver', $codigos);
        $this->assertContains('gestiones.crear', $codigos);
    }

    public function test_eliminar_rol_con_asignaciones_activas_falla(): void
    {
        $proyectoId = $this->proyectoVenta();
        $this->loginAdminGlobal($proyectoId);

        $rolId = $this->crearRolCustom($proyectoId, 'CUSTOM_DEL', ['casos.ver']);

        $usuarioId = User::query()->create([
            'name' => 'Asign', 'email' => 'asign.'.Str::random(4).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ])->id;

        DB::table('usuario_proyecto_rol_custom')->insert([
            'usuario_id' => $usuarioId,
            'proyecto_id' => $proyectoId,
            'rol_custom_id' => $rolId,
            'activo' => true,
        ]);

        Livewire::test(AdminRolesCustom::class)
            ->call('eliminar', $rolId);

        $row = DB::table('roles_custom')->where('id', $rolId)->first();
        $this->assertNotNull($row);
        $this->assertNull($row->eliminada_en, 'El rol no debió ser eliminado');
    }

    public function test_eliminar_rol_sin_asignaciones_marca_eliminada_en(): void
    {
        $proyectoId = $this->proyectoVenta();
        $this->loginAdminGlobal($proyectoId);

        $rolId = $this->crearRolCustom($proyectoId, 'CUSTOM_OK', ['casos.ver']);

        Livewire::test(AdminRolesCustom::class)
            ->call('eliminar', $rolId);

        $row = DB::table('roles_custom')->where('id', $rolId)->first();
        $this->assertNotNull($row->eliminada_en);
    }

    public function test_multi_tenancy_rol_de_proyecto_a_no_aparece_en_proyecto_b(): void
    {
        $proyectoA = $this->proyectoVenta();
        $proyectoB = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');

        $this->crearRolCustom($proyectoA, 'SOLO_A', ['casos.ver']);
        $this->crearRolCustom($proyectoB, 'SOLO_B', ['casos.ver']);

        $this->loginAdminGlobal($proyectoB);

        $count = DB::table('roles_custom')
            ->where('proyecto_id', $proyectoB)
            ->whereNull('eliminada_en')
            ->count();

        $this->assertSame(1, $count);

        $codigos = DB::table('roles_custom')
            ->where('proyecto_id', $proyectoB)
            ->whereNull('eliminada_en')
            ->pluck('codigo')
            ->all();
        $this->assertContains('SOLO_B', $codigos);
        $this->assertNotContains('SOLO_A', $codigos);
    }

    public function test_usuario_con_rol_custom_obtiene_permisos_via_tienepermiso(): void
    {
        $proyectoId = $this->proyectoVenta();
        $rolId = $this->crearRolCustom($proyectoId, 'TELEVENTAS', [
            'casos.ver', 'gestiones.crear', 'compromisos.crear',
        ]);

        $usuario = User::query()->create([
            'name' => 'Juan', 'email' => 'juan.tv.'.Str::random(4).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);
        DB::table('usuario_proyecto_rol_custom')->insert([
            'usuario_id' => $usuario->id,
            'proyecto_id' => $proyectoId,
            'rol_custom_id' => $rolId,
            'activo' => true,
        ]);

        $this->assertTrue($usuario->tienePermiso('casos.ver', $proyectoId));
        $this->assertTrue($usuario->tienePermiso('gestiones.crear', $proyectoId));
        $this->assertTrue($usuario->tienePermiso('compromisos.crear', $proyectoId));
        $this->assertFalse($usuario->tienePermiso('reportes.exportar', $proyectoId));
        $this->assertFalse($usuario->tienePermiso('roles.gestionar', $proyectoId));
    }

    public function test_usuario_con_rol_custom_no_accede_admin_roles_custom(): void
    {
        $proyectoId = $this->proyectoVenta();
        $rolId = $this->crearRolCustom($proyectoId, 'CUSTOM_OP', ['casos.ver', 'gestiones.crear']);

        $usuario = User::query()->create([
            'name' => 'Op', 'email' => 'op.'.Str::random(4).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);
        DB::table('usuario_proyecto_rol_custom')->insert([
            'usuario_id' => $usuario->id,
            'proyecto_id' => $proyectoId,
            'rol_custom_id' => $rolId,
            'activo' => true,
        ]);

        $this->actingAs($usuario)
            ->get(route('proyectos.admin.roles-custom', ['proyecto_id' => $proyectoId]))
            ->assertStatus(403);
    }

    public function test_tienepermiso_combina_rol_base_y_custom(): void
    {
        $proyectoId = $this->proyectoVenta();
        // GESTOR base aporta casos.ver
        $usuario = $this->crearConRol($proyectoId, 'GESTOR');

        // Rol custom aporta reportes.operativos (que GESTOR base NO tiene)
        $rolId = $this->crearRolCustom($proyectoId, 'EXTRA', ['reportes.operativos']);
        DB::table('usuario_proyecto_rol_custom')->insert([
            'usuario_id' => $usuario->id,
            'proyecto_id' => $proyectoId,
            'rol_custom_id' => $rolId,
            'activo' => true,
        ]);

        $this->assertTrue($usuario->tienePermiso('casos.ver', $proyectoId));        // base
        $this->assertTrue($usuario->tienePermiso('gestiones.crear', $proyectoId));  // base
        $this->assertTrue($usuario->tienePermiso('reportes.operativos', $proyectoId)); // custom
    }

    public function test_asignar_rol_custom_via_gestion_usuarios_proyecto(): void
    {
        $proyectoId = $this->proyectoVenta();
        $rolId = $this->crearRolCustom($proyectoId, 'CUSTOM_VIA_F10', ['casos.ver']);

        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($supervisor);

        $target = User::query()->create([
            'name' => 'Tg', 'email' => 'tg.'.Str::random(4).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);

        Livewire::test(GestionUsuariosProyecto::class)
            ->call('abrirFormAsignar')
            ->set('buscarEmail', $target->email)
            ->call('buscarUsuario')
            ->set('rolAsignarValor', 'custom:'.$rolId)
            ->call('asignar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('usuario_proyecto_rol_custom', [
            'usuario_id' => $target->id,
            'proyecto_id' => $proyectoId,
            'rol_custom_id' => $rolId,
            'activo' => true,
        ]);
    }

    public function test_revocar_rol_custom_via_gestion_usuarios_proyecto(): void
    {
        $proyectoId = $this->proyectoVenta();
        $rolId = $this->crearRolCustom($proyectoId, 'CUSTOM_REV', ['casos.ver']);

        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($supervisor);

        $target = User::query()->create([
            'name' => 'Rev', 'email' => 'rev.'.Str::random(4).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);
        DB::table('usuario_proyecto_rol_custom')->insert([
            'usuario_id' => $target->id, 'proyecto_id' => $proyectoId,
            'rol_custom_id' => $rolId, 'activo' => true,
        ]);

        Livewire::test(GestionUsuariosProyecto::class)
            ->call('quitarCustom', $target->id, $rolId);

        $this->assertDatabaseMissing('usuario_proyecto_rol_custom', [
            'usuario_id' => $target->id,
            'rol_custom_id' => $rolId,
        ]);
    }

    public function test_matriz_permisos_accesible_solo_admin_global(): void
    {
        $proyectoId = $this->proyectoVenta();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');

        $this->actingAs($supervisor)
            ->get(route('proyectos.admin.matriz-permisos', ['proyecto_id' => $proyectoId]))
            ->assertStatus(403);

        $this->loginAdminGlobal($proyectoId);
        $this->get(route('proyectos.admin.matriz-permisos', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);
    }

    private function proyectoVenta(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'VENTA_DEMO_2026')->value('id');
    }

    private function adminId(): int
    {
        return (int) DB::table('users')->where('email', 'admin@crm.local')->value('id');
    }

    private function loginAdminGlobal(int $proyectoId): void
    {
        $admin = User::query()->where('email', 'admin@crm.local')->firstOrFail();
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($admin);
    }

    private function bindProyectoActivo(int $proyectoId): void
    {
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));
    }

    private function crearConRol(int $proyectoId, string $codigoRol): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name' => ucfirst(strtolower($codigoRol)),
            'email' => strtolower($codigoRol).'.f33.'.Str::random(6).'@crm.local',
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

    /**
     * @param  list<string>  $codigosPermisos
     */
    private function crearRolCustom(int $proyectoId, string $codigo, array $codigosPermisos): int
    {
        $useCase = $this->app->make(CrearRolCustom::class);

        return $useCase->execute(
            new EntradaRolCustom($proyectoId, $codigo, ucfirst(strtolower($codigo)), null, $codigosPermisos),
            $this->adminId(),
        );
    }
}
