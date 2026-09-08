<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Usuarios;

use App\Models\User;
use App\Modules\Usuarios\Infrastructure\Http\Livewire\GestionUsuariosProyecto;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Esta pantalla reparte roles, y el `can:usuarios.gestionar` de la ruta sólo
 * protegía la página. Cada acción de Livewire es un POST aparte a
 * /livewire/update que vuelve a entrar en el componente con las propiedades que
 * mande el cliente y sin volver a pasar por el middleware: asignar, quitar y
 * quitarCustom escribían sin comprobar ni permiso ni pertenencia, y el id del
 * usuario destino era elegible desde la consola del navegador, saltándose las
 * dos negativas de `buscarUsuario` (ADMIN_GLOBAL y usuario de otro mandante).
 */
final class GestionUsuariosProyectoAutorizacionTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_auditor_no_puede_asignar_un_rol(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearAuditor($proyecto));

        $target = $this->crearCuentaSuelta();
        $rolGestorId = $this->rolBaseId('GESTOR');

        // El AUDITOR tiene `usuarios.ver`, no `usuarios.gestionar`: puede leer
        // quién opera el proyecto, no repartir roles.
        Livewire::test(GestionUsuariosProyecto::class)
            ->set('buscarEmail', (string) $target->email)
            ->call('buscarUsuario')
            ->assertForbidden();

        $this->assertDatabaseMissing('usuario_proyecto_rol', [
            'usuario_id' => $target->id,
            'proyecto_id' => $proyecto->id,
            'rol_id' => $rolGestorId,
        ]);
    }

    public function test_auditor_no_puede_quitar_el_rol_de_otro(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearAuditor($proyecto));

        $rolGestorId = $this->rolBaseId('GESTOR');

        Livewire::test(GestionUsuariosProyecto::class)
            ->call('quitar', $gestor->id, $rolGestorId)
            ->assertForbidden();

        $this->assertDatabaseHas('usuario_proyecto_rol', [
            'usuario_id' => $gestor->id,
            'proyecto_id' => $proyecto->id,
            'rol_id' => $rolGestorId,
        ]);
    }

    public function test_gestor_no_puede_asignarse_a_si_mismo_el_rol_de_supervisor(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);
        $this->activarProyecto($proyecto);
        $this->actingAs($gestor);

        $rolSupervisorId = $this->rolBaseId('SUPERVISOR');

        // El GESTOR no tiene ningún permiso del grupo `usuarios`. Sin la guarda,
        // el commit de Livewire le bastaba para promocionarse.
        Livewire::test(GestionUsuariosProyecto::class)
            ->call('quitar', $gestor->id, $rolSupervisorId)
            ->assertForbidden();

        $this->assertDatabaseMissing('usuario_proyecto_rol', [
            'usuario_id' => $gestor->id,
            'proyecto_id' => $proyecto->id,
            'rol_id' => $rolSupervisorId,
        ]);
    }

    public function test_supervisor_si_puede_asignar_y_quitar(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        $target = $this->crearCuentaSuelta();
        $rolGestorId = $this->rolBaseId('GESTOR');

        Livewire::test(GestionUsuariosProyecto::class)
            ->call('abrirFormAsignar')
            ->set('buscarEmail', (string) $target->email)
            ->call('buscarUsuario')
            ->set('rolAsignarValor', 'base:'.$rolGestorId)
            ->call('asignar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('usuario_proyecto_rol', [
            'usuario_id' => $target->id,
            'proyecto_id' => $proyecto->id,
            'rol_id' => $rolGestorId,
            'activo' => true,
        ]);

        Livewire::test(GestionUsuariosProyecto::class)
            ->call('quitar', $target->id, $rolGestorId);

        $this->assertDatabaseMissing('usuario_proyecto_rol', [
            'usuario_id' => $target->id,
            'proyecto_id' => $proyecto->id,
            'rol_id' => $rolGestorId,
        ]);
    }

    public function test_no_se_puede_asignar_un_rol_custom_de_otro_proyecto(): void
    {
        $propio = $this->crearProyectoCobranza();
        $ajeno = $this->crearProyectoCobranza();          // otro mandante
        $rolAjenoId = $this->crearRolCustomEn($ajeno, 'CUSTOM_AJENO');

        $this->activarProyecto($propio);
        $this->actingAs($this->crearSupervisor($propio));

        $target = $this->crearCuentaSuelta();

        // El supervisor tiene `usuarios.gestionar` EN SU PROYECTO. Si sólo se
        // comprobara el permiso, arrastraría esa autorización al rol ajeno.
        Livewire::test(GestionUsuariosProyecto::class)
            ->set('buscarEmail', (string) $target->email)
            ->call('buscarUsuario')
            ->set('rolAsignarValor', 'custom:'.$rolAjenoId)
            ->call('asignar')
            ->assertNotFound();

        $this->assertDatabaseMissing('usuario_proyecto_rol_custom', [
            'usuario_id' => $target->id,
            'rol_custom_id' => $rolAjenoId,
        ]);
    }

    public function test_no_se_puede_revocar_un_rol_custom_de_otro_proyecto(): void
    {
        $propio = $this->crearProyectoCobranza();
        $ajeno = $this->crearProyectoCobranza();
        $rolAjenoId = $this->crearRolCustomEn($ajeno, 'CUSTOM_REV_AJENO');

        $victima = $this->crearCuentaSuelta();
        DB::table('usuario_proyecto_rol_custom')->insert([
            'usuario_id' => $victima->id,
            'proyecto_id' => $ajeno->id,
            'rol_custom_id' => $rolAjenoId,
            'activo' => true,
        ]);

        $this->activarProyecto($propio);
        $this->actingAs($this->crearSupervisor($propio));

        Livewire::test(GestionUsuariosProyecto::class)
            ->call('quitarCustom', $victima->id, $rolAjenoId)
            ->assertNotFound();

        $this->assertDatabaseHas('usuario_proyecto_rol_custom', [
            'usuario_id' => $victima->id,
            'proyecto_id' => $ajeno->id,
            'rol_custom_id' => $rolAjenoId,
        ]);
    }

    public function test_supervisor_si_puede_asignar_y_revocar_el_rol_custom_de_su_proyecto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $rolId = $this->crearRolCustomEn($proyecto, 'CUSTOM_PROPIO');

        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        $target = $this->crearCuentaSuelta();

        Livewire::test(GestionUsuariosProyecto::class)
            ->set('buscarEmail', (string) $target->email)
            ->call('buscarUsuario')
            ->set('rolAsignarValor', 'custom:'.$rolId)
            ->call('asignar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('usuario_proyecto_rol_custom', [
            'usuario_id' => $target->id,
            'proyecto_id' => $proyecto->id,
            'rol_custom_id' => $rolId,
            'activo' => true,
        ]);

        Livewire::test(GestionUsuariosProyecto::class)
            ->call('quitarCustom', $target->id, $rolId);

        $this->assertDatabaseMissing('usuario_proyecto_rol_custom', [
            'usuario_id' => $target->id,
            'rol_custom_id' => $rolId,
        ]);
    }

    public function test_el_usuario_destino_no_se_puede_reapuntar_desde_el_cliente(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        $admin = User::query()->where('email', 'admin@crm.local')->firstOrFail();

        // #[Locked]: sin esto, saltarse `buscarUsuario()` era saltarse el filtro
        // de ADMIN_GLOBAL y el de «usuario de otro mandante».
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(GestionUsuariosProyecto::class)
            ->set('usuarioBuscadoId', (int) $admin->id);
    }

    private function crearCuentaSuelta(): User
    {
        return User::query()->create([
            'name' => 'Cuenta Suelta',
            'email' => 'suelta.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);
    }

    private function rolBaseId(string $codigo): int
    {
        return (int) DB::table('roles')->where('codigo', $codigo)->value('id');
    }

    private function crearRolCustomEn(stdClass $proyecto, string $codigo): int
    {
        return (int) DB::table('roles_custom')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'codigo' => $codigo,
            'nombre' => $codigo,
            'activo' => true,
            'creado_por_usuario_id' => $this->crearAdminGlobal()->id,
            'creada_en' => now(),
            'actualizada_en' => now(),
        ]);
    }
}
