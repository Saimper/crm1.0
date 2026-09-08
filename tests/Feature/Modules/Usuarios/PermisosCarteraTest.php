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
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Fase 22: evalúa el nuevo scope por cartera en el sistema de permisos.
 *
 * Semántica:
 *   - Sin filas en usuario_proyecto_rol_cartera → rol aplica a TODO el proyecto.
 *   - Con filas → rol aplica SOLO a las carteras listadas.
 */
final class PermisosCarteraTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_sin_restriccion_aplica_a_todas_las_carteras(): void
    {
        $proyecto = $this->proyectoConCarteras();
        $proyectoId = (int) $proyecto->id;
        $gestor = $this->crearUsuarioConRol($proyecto, 'GESTOR');

        $carteras = DB::table('carteras')->where('proyecto_id', $proyectoId)->pluck('id')->all();
        $this->assertNotEmpty($carteras);

        foreach ($carteras as $carteraId) {
            $this->assertTrue(
                $gestor->tienePermiso('gestiones.crear', $proyectoId, (int) $carteraId),
                "Sin restricción debería tener permiso en cartera {$carteraId}",
            );
        }
    }

    public function test_con_restriccion_solo_aplica_a_carteras_listadas(): void
    {
        $proyecto = $this->proyectoConCarteras();
        $proyectoId = (int) $proyecto->id;
        $gestor = $this->crearUsuarioConRol($proyecto, 'GESTOR');

        $carteras = DB::table('carteras')->where('proyecto_id', $proyectoId)->pluck('id')->all();
        $this->assertGreaterThanOrEqual(2, count($carteras));
        $carteraPermitida = (int) $carteras[0];
        $carteraDenegada = (int) $carteras[1];

        $rolGestorId = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');

        DB::table('usuario_proyecto_rol_cartera')->insert([
            'usuario_id' => $gestor->id,
            'proyecto_id' => $proyectoId,
            'rol_id' => $rolGestorId,
            'cartera_id' => $carteraPermitida,
        ]);

        $this->assertTrue(
            $gestor->tienePermiso('gestiones.crear', $proyectoId, $carteraPermitida),
            'Debería tener permiso en la cartera permitida',
        );
        $this->assertFalse(
            $gestor->tienePermiso('gestiones.crear', $proyectoId, $carteraDenegada),
            'NO debería tener permiso en cartera no listada',
        );
    }

    public function test_sin_cartera_especificada_ignora_restricciones(): void
    {
        $proyecto = $this->proyectoConCarteras();
        $proyectoId = (int) $proyecto->id;
        $gestor = $this->crearUsuarioConRol($proyecto, 'GESTOR');

        $carteraId = (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->value('id');
        $rolGestorId = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');

        DB::table('usuario_proyecto_rol_cartera')->insert([
            'usuario_id' => $gestor->id,
            'proyecto_id' => $proyectoId,
            'rol_id' => $rolGestorId,
            'cartera_id' => $carteraId,
        ]);

        // Al llamar sin cartera se evalúa sólo a nivel proyecto → permiso sigue activo.
        $this->assertTrue($gestor->tienePermiso('gestiones.crear', $proyectoId));
    }

    public function test_admin_global_ignora_restriccion_de_cartera(): void
    {
        $proyecto = $this->proyectoConCarteras();
        $proyectoId = (int) $proyecto->id;
        $admin = $this->crearAdminGlobal();

        $carteraId = (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->value('id');

        $this->assertTrue($admin->tienePermiso('campos.definir', $proyectoId, $carteraId));
        $this->assertTrue($admin->tienePermiso('cualquier.permiso.inventado', $proyectoId, 999));
    }

    public function test_livewire_asigna_rol_con_carteras(): void
    {
        $proyecto = $this->proyectoConCarteras();
        $proyectoId = (int) $proyecto->id;
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearUsuarioConRol($proyecto, 'SUPERVISOR'));

        $nuevo = User::query()->create([
            'name' => 'NuevoGestor', 'email' => 'nuevo.gestor.'.Str::random(4).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);
        $rolGestorId = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');
        $carteras = DB::table('carteras')->where('proyecto_id', $proyectoId)->pluck('id')->all();
        $this->assertGreaterThanOrEqual(1, count($carteras));

        Livewire::test(GestionUsuariosProyecto::class)
            ->call('abrirFormAsignar')
            ->set('buscarEmail', $nuevo->email)
            ->call('buscarUsuario')
            ->set('rolAsignarValor', 'base:'.$rolGestorId)
            ->set('carterasSeleccionadas', [(int) $carteras[0]])
            ->call('asignar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('usuario_proyecto_rol', [
            'usuario_id' => $nuevo->id,
            'proyecto_id' => $proyectoId,
            'rol_id' => $rolGestorId,
        ]);
        $this->assertDatabaseHas('usuario_proyecto_rol_cartera', [
            'usuario_id' => $nuevo->id,
            'proyecto_id' => $proyectoId,
            'rol_id' => $rolGestorId,
            'cartera_id' => $carteras[0],
        ]);
    }

    public function test_reasignar_reemplaza_restricciones_de_cartera(): void
    {
        $proyecto = $this->proyectoConCarteras();
        $proyectoId = (int) $proyecto->id;
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearUsuarioConRol($proyecto, 'SUPERVISOR'));

        $u = User::query()->create([
            'name' => 'Reasig', 'email' => 'reasig.'.Str::random(4).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);
        $rolGestorId = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');
        $carteras = DB::table('carteras')->where('proyecto_id', $proyectoId)->pluck('id')->all();

        // Primera asignación: cartera[0]
        Livewire::test(GestionUsuariosProyecto::class)
            ->call('abrirFormAsignar')
            ->set('buscarEmail', $u->email)
            ->call('buscarUsuario')
            ->set('rolAsignarValor', 'base:'.$rolGestorId)
            ->set('carterasSeleccionadas', [(int) $carteras[0]])
            ->call('asignar');

        // Reasignación: ninguna cartera (aplica a todo el proyecto)
        Livewire::test(GestionUsuariosProyecto::class)
            ->call('abrirFormAsignar')
            ->set('buscarEmail', $u->email)
            ->call('buscarUsuario')
            ->set('rolAsignarValor', 'base:'.$rolGestorId)
            ->set('carterasSeleccionadas', [])
            ->call('asignar');

        $this->assertSame(0, (int) DB::table('usuario_proyecto_rol_cartera')
            ->where('usuario_id', $u->id)->where('rol_id', $rolGestorId)->count());
    }

    /**
     * El escenario que antes venía del seeder demo: un proyecto de cobranza con
     * dos carteras, que es lo mínimo para distinguir «permitida» de «denegada».
     */
    private function proyectoConCarteras(): stdClass
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->crearCarteraEn($proyecto, 'CONSUMO');
        $this->crearCarteraEn($proyecto, 'HIPOTECARIO');

        return $proyecto;
    }
}
