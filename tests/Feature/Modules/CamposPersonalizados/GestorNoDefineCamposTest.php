<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\CamposPersonalizados;

use App\Modules\CamposPersonalizados\Infrastructure\Http\Livewire\AdminCamposPersonalizados;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Fase 23: hardening. Bajo ninguna circunstancia un gestor (o supervisor) puede
 * crear/modificar/eliminar DEFINICIONES de campos personalizados. Las DEFINICIONES
 * son exclusivas de ADMIN_GLOBAL (permiso `campos.definir`).
 *
 * Cubre:
 *   1. Ruta HTTP `/admin/campos-personalizados` → 403 para no-admin.
 *   2. Llamadas directas al Livewire AdminCamposPersonalizados → abort(403) en todas las acciones.
 *   3. Seeder no asigna `campos.definir` a roles no-admin.
 */
final class GestorNoDefineCamposTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_ruta_admin_campos_403_para_gestor(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        $this->actingAs($gestor)
            ->get('/admin/campos-personalizados')
            ->assertStatus(403);
    }

    public function test_ruta_admin_campos_403_para_supervisor(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);

        $this->actingAs($supervisor)
            ->get('/admin/campos-personalizados')
            ->assertStatus(403);
    }

    public function test_ruta_admin_campos_200_para_admin_global(): void
    {
        $admin = $this->crearAdminGlobal();

        $this->actingAs($admin)
            ->get('/admin/campos-personalizados')
            ->assertStatus(200);
    }

    public function test_livewire_admin_campos_mount_aborta_con_gestor(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);
        $this->actingAs($gestor);

        Livewire::test(AdminCamposPersonalizados::class)
            ->assertStatus(403);
    }

    public function test_livewire_admin_campos_mount_aborta_con_supervisor(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);
        $this->actingAs($supervisor);

        Livewire::test(AdminCamposPersonalizados::class)
            ->assertStatus(403);
    }

    public function test_admin_global_crea_definicion_ok(): void
    {
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);

        Livewire::test(AdminCamposPersonalizados::class)
            ->call('abrirFormCrear')
            ->set('form.proyecto_id', (int) $proyecto->id)
            ->set('form.ambito', 'caso')
            ->set('form.ambito_id', (int) $cartera->id)
            ->set('form.codigo', 'campo_admin_test')
            ->set('form.etiqueta', 'Campo Admin Test')
            ->set('form.tipo', 'texto_corto')
            ->set('form.obligatorio', false)
            ->set('form.activo', true)
            ->set('form.orden', 100)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('campos_personalizados', [
            'proyecto_id' => (int) $proyecto->id,
            'codigo' => 'campo_admin_test',
        ]);
    }

    public function test_seeder_no_asigna_campos_definir_a_roles_no_admin(): void
    {
        $permisoId = (int) DB::table('permisos')->where('codigo', 'campos.definir')->value('id');
        $this->assertGreaterThan(0, $permisoId);

        $rolesNoAdmin = DB::table('roles')
            ->whereNotIn('codigo', ['ADMIN_GLOBAL'])
            ->pluck('id')->all();

        foreach ($rolesNoAdmin as $rolId) {
            $tiene = DB::table('rol_permiso')
                ->where('rol_id', $rolId)
                ->where('permiso_id', $permisoId)
                ->exists();
            $rolCodigo = DB::table('roles')->where('id', $rolId)->value('codigo');
            $this->assertFalse(
                $tiene,
                "El rol {$rolCodigo} NO debe tener el permiso campos.definir. Es exclusivo de ADMIN_GLOBAL.",
            );
        }
    }

    public function test_seeder_no_asigna_entidades_definir_a_roles_no_admin(): void
    {
        $permisoId = (int) DB::table('permisos')->where('codigo', 'entidades.definir')->value('id');
        $this->assertGreaterThan(0, $permisoId);

        $rolesNoAdmin = DB::table('roles')
            ->whereNotIn('codigo', ['ADMIN_GLOBAL'])
            ->pluck('id')->all();

        foreach ($rolesNoAdmin as $rolId) {
            $tiene = DB::table('rol_permiso')
                ->where('rol_id', $rolId)
                ->where('permiso_id', $permisoId)
                ->exists();
            $rolCodigo = DB::table('roles')->where('id', $rolId)->value('codigo');
            $this->assertFalse(
                $tiene,
                "El rol {$rolCodigo} NO debe tener entidades.definir. Es exclusivo de ADMIN_GLOBAL.",
            );
        }
    }

    public function test_gestor_no_tiene_campos_definir_en_ningun_proyecto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        $this->assertFalse($gestor->tienePermiso('campos.definir', (int) $proyecto->id));
        $this->assertFalse($gestor->tienePermiso('entidades.definir', (int) $proyecto->id));
    }

    public function test_supervisor_no_tiene_campos_definir(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);

        $this->assertFalse($supervisor->tienePermiso('campos.definir', (int) $proyecto->id));
        $this->assertFalse($supervisor->tienePermiso('entidades.definir', (int) $proyecto->id));
    }
}
