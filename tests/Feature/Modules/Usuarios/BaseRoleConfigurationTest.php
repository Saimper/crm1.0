<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Usuarios;

use App\Models\User;
use App\Modules\Usuarios\Application\RolesBase\GuardarRolBase;
use App\Modules\Usuarios\Domain\RolesBase\ConfiguracionRolBase;
use App\Modules\Usuarios\Infrastructure\Http\Livewire\AdminRolesBase;
use App\Modules\Usuarios\Infrastructure\Http\Livewire\MatrizPermisos;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Usuarios\PermisosSeeder;
use Database\Seeders\Usuarios\RolesSeeder;
use Database\Seeders\Usuarios\RolPermisoSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class BaseRoleConfigurationTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = $this->crearAdminGlobal();
        $this->actingAs($this->admin);
    }

    public function test_project_grants_denials_and_reset_do_not_leak_and_invalidate_cached_permissions(): void
    {
        $a = $this->crearProyectoCobranza();
        $b = $this->crearProyectoCobranza();
        $usuario = $this->crearGestor($a);
        DB::table('usuario_proyecto_rol')->insert(['usuario_id' => $usuario->id, 'proyecto_id' => $b->id, 'rol_id' => $this->rolId('GESTOR'), 'activo' => true]);
        self::assertTrue($usuario->tienePermiso('casos.ver', (int) $a->id));
        self::assertFalse($usuario->tienePermiso('historico.ver', (int) $a->id));

        $this->guardar(ConfiguracionRolBase::proyecto('GESTOR', (int) $a->id, ['casos.ver' => 'denegar', 'historico.ver' => 'permitir']));
        self::assertFalse($usuario->tienePermiso('casos.ver', (int) $a->id));
        self::assertTrue($usuario->tienePermiso('historico.ver', (int) $a->id));
        self::assertTrue($usuario->tienePermiso('casos.ver', (int) $b->id));
        self::assertFalse($usuario->tienePermiso('historico.ver', (int) $b->id));

        $this->guardar(ConfiguracionRolBase::proyecto('GESTOR', (int) $a->id, []));
        self::assertTrue($usuario->tienePermiso('casos.ver', (int) $a->id));
        self::assertFalse($usuario->tienePermiso('historico.ver', (int) $a->id));
    }

    public function test_saved_global_template_and_project_decisions_survive_deployment_seeders(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $usuario = $this->crearGestor($proyecto);
        $this->guardar(ConfiguracionRolBase::proyecto('GESTOR', (int) $proyecto->id, ['historico.ver' => 'denegar', 'casos.colaborar' => 'permitir']));
        $this->guardar(ConfiguracionRolBase::plantilla('GESTOR', 'Asesor operativo', 'Configurado por administración', ['historico.ver']));
        $this->seed([RolesSeeder::class, PermisosSeeder::class, RolPermisoSeeder::class]);

        self::assertSame('Asesor operativo', DB::table('roles')->where('codigo', 'GESTOR')->value('nombre'));
        self::assertFalse($usuario->tienePermiso('gestiones.crear', (int) $proyecto->id));
        self::assertFalse($usuario->tienePermiso('historico.ver', (int) $proyecto->id));
        self::assertTrue($usuario->tienePermiso('casos.colaborar', (int) $proyecto->id));
        self::assertTrue($this->admin->tienePermiso('roles.gestionar', (int) $proyecto->id));
        $this->assertDatabaseHas('auditorias', ['entidad_tipo' => 'roles', 'entidad_id' => $this->rolId('GESTOR'), 'usuario_id' => $this->admin->id]);
        $this->assertDatabaseHas('auditorias', ['entidad_tipo' => 'rol_proyecto_permiso', 'proyecto_id' => $proyecto->id, 'usuario_id' => $this->admin->id]);
    }

    public function test_project_grant_keeps_portfolio_restrictions_and_inactive_permissions_are_denied(): void
    {
        $p = $this->crearProyectoCobranza();
        $a = $this->crearCarteraEn($p);
        $b = $this->crearCarteraEn($p);
        $usuario = $this->crearGestor($p);
        DB::table('usuario_proyecto_rol_cartera')->insert(['usuario_id' => $usuario->id, 'proyecto_id' => $p->id, 'rol_id' => $this->rolId('GESTOR'), 'cartera_id' => $a->id]);
        $this->guardar(ConfiguracionRolBase::proyecto('GESTOR', (int) $p->id, ['historico.ver' => 'permitir']));
        self::assertTrue($usuario->tienePermiso('historico.ver', (int) $p->id, (int) $a->id));
        self::assertFalse($usuario->tienePermiso('historico.ver', (int) $p->id, (int) $b->id));
        DB::table('permisos')->where('codigo', 'historico.ver')->update(['activo' => false]);
        self::assertFalse($usuario->tienePermiso('historico.ver', (int) $p->id, (int) $a->id));
    }

    public function test_supervisor_cannot_edit_base_roles_even_by_direct_use_case(): void
    {
        $p = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($p);
        $this->expectException(AuthorizationException::class);
        app(GuardarRolBase::class)->execute(ConfiguracionRolBase::proyecto('GESTOR', (int) $p->id, ['historico.ver' => 'permitir']), (int) $supervisor->id);
    }

    public function test_editor_saves_project_decision_and_matrix_shows_effective_permission(): void
    {
        $p = $this->crearProyectoCobranza();
        $this->activarProyecto($p);
        $permiso = (int) DB::table('permisos')->where('codigo', 'historico.ver')->value('id');
        Livewire::test(AdminRolesBase::class)->call('editar', 'GESTOR', 'proyecto')
            ->set('decisiones', [$permiso => 'permitir'])->call('guardar')->assertHasNoErrors();
        Livewire::test(MatrizPermisos::class)->set('busqueda', 'historico.ver')
            ->assertViewHas('rolPermisoBase', fn ($matrix): bool => in_array($permiso, $matrix->get($this->rolId('GESTOR')), true))
            ->assertSee('Consultar cuentas del histórico');
    }

    public function test_forged_privileged_decision_is_rejected_without_writing(): void
    {
        $p = $this->crearProyectoCobranza();
        $this->activarProyecto($p);
        $permiso = (int) DB::table('permisos')->where('codigo', 'roles.gestionar')->value('id');
        Livewire::test(AdminRolesBase::class)->call('editar', 'GESTOR', 'proyecto')
            ->set('decisiones', [$permiso => 'permitir'])->call('guardar')->assertHasErrors('configuracion');
        $this->assertDatabaseMissing('rol_proyecto_permiso', ['proyecto_id' => $p->id, 'permiso_id' => $permiso]);
    }

    private function guardar(ConfiguracionRolBase $entrada): void
    {
        app(GuardarRolBase::class)->execute($entrada, (int) $this->admin->id);
    }

    private function rolId(string $codigo): int
    {
        return (int) DB::table('roles')->where('codigo', $codigo)->value('id');
    }
}
