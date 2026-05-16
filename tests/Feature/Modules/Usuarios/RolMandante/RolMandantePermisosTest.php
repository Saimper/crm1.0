<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Usuarios\RolMandante;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class RolMandantePermisosTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_mandante_puede_ver_casos_en_cualquier_proyecto_del_mandante(): void
    {
        $mandante = $this->crearMandante();
        $proyectoA = $this->crearProyectoCobranza($mandante);
        $proyectoB = $this->crearProyectoCx($mandante);

        $admin = $this->crearAdminMandante($mandante);

        $this->assertTrue($admin->tienePermiso('casos.ver', (int) $proyectoA->id));
        $this->assertTrue($admin->tienePermiso('casos.ver', (int) $proyectoB->id));
    }

    public function test_admin_mandante_no_tiene_acceso_a_proyecto_de_otro_mandante(): void
    {
        $mandanteA = $this->crearMandante();
        $mandanteB = $this->crearMandante();
        $proyectoB = $this->crearProyectoCobranza($mandanteB);

        $admin = $this->crearAdminMandante($mandanteA);

        $this->assertFalse($admin->tienePermiso('casos.ver', (int) $proyectoB->id));
        $this->assertFalse($admin->tieneAccesoAProyecto((int) $proyectoB->id));
    }

    public function test_admin_mandante_puede_administrar_mandante(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $admin = $this->crearAdminMandante($mandante);

        $this->assertTrue($admin->tienePermiso('mandante.administrar', (int) $proyecto->id));
        $this->assertTrue($admin->tienePermiso('proyectos.crear', (int) $proyecto->id));
        $this->assertTrue($admin->tienePermiso('proyectos.configurar', (int) $proyecto->id));
    }

    public function test_admin_mandante_no_define_campos_personalizados(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $admin = $this->crearAdminMandante($mandante);

        // §13.16 / §7.7: definir es exclusivo ADMIN_GLOBAL.
        $this->assertFalse($admin->tienePermiso('campos.definir', (int) $proyecto->id));
        $this->assertFalse($admin->tienePermiso('entidades.definir', (int) $proyecto->id));
        $this->assertFalse($admin->tienePermiso('roles.gestionar', (int) $proyecto->id));
    }

    public function test_supervisor_proyecto_no_gana_permisos_cross_proyecto_del_mandante(): void
    {
        $mandante = $this->crearMandante();
        $proyectoA = $this->crearProyectoCobranza($mandante);
        $proyectoB = $this->crearProyectoCx($mandante);

        $supervisor = $this->crearSupervisor($proyectoA);

        // SUPERVISOR sigue scoped al proyecto A.
        $this->assertTrue($supervisor->tienePermiso('casos.ver', (int) $proyectoA->id));
        $this->assertFalse($supervisor->tienePermiso('casos.ver', (int) $proyectoB->id));
    }

    public function test_admin_mandante_y_supervisor_coexisten_en_mismo_proyecto(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);

        $admin = $this->crearAdminMandante($mandante);
        $supervisor = $this->crearSupervisor($proyecto);

        // Cada uno tiene su propia ruta de evaluación; no se interfieren.
        $this->assertTrue($admin->tienePermiso('casos.ver', (int) $proyecto->id));
        $this->assertTrue($supervisor->tienePermiso('casos.ver', (int) $proyecto->id));
    }

    public function test_admin_mandante_inactivo_pierde_permisos(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $admin = $this->crearAdminMandante($mandante);

        DB::table('usuario_mandante_rol')
            ->where('usuario_id', $admin->id)
            ->where('mandante_id', $mandante->id)
            ->update(['activo' => false]);

        $this->assertFalse($admin->tienePermiso('casos.ver', (int) $proyecto->id));
        $this->assertFalse($admin->tieneAccesoAProyecto((int) $proyecto->id));
    }

    public function test_mandantes_administrados_devuelve_lista_correcta(): void
    {
        $mandanteA = $this->crearMandante();
        $mandanteB = $this->crearMandante();
        $admin = $this->crearAdminMandante($mandanteA);

        $rolId = (int) DB::table('roles')->where('codigo', 'ADMIN_MANDANTE')->value('id');
        DB::table('usuario_mandante_rol')->insert([
            'usuario_id' => $admin->id,
            'mandante_id' => $mandanteB->id,
            'rol_id' => $rolId,
            'activo' => true,
        ]);

        $mandantes = $admin->mandantesAdministrados();
        sort($mandantes);

        $expected = [(int) $mandanteA->id, (int) $mandanteB->id];
        sort($expected);

        $this->assertSame($expected, $mandantes);
    }

    private function crearAdminMandante(\stdClass $mandante): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name' => 'Admin Mandante',
            'email' => 'admin.mand.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);

        $rolId = (int) DB::table('roles')->where('codigo', 'ADMIN_MANDANTE')->value('id');
        DB::table('usuario_mandante_rol')->insert([
            'usuario_id' => $u->id,
            'mandante_id' => $mandante->id,
            'rol_id' => $rolId,
            'activo' => true,
        ]);

        return $u;
    }
}
