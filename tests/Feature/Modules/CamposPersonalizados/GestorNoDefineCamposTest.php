<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\CamposPersonalizados;

use App\Models\User;
use App\Modules\CamposPersonalizados\Infrastructure\Http\Livewire\AdminCamposPersonalizados;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
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
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_ruta_admin_campos_403_para_gestor(): void
    {
        $proyectoId = $this->proyectoId();
        $gestor = $this->crearConRol($proyectoId, 'GESTOR');

        $this->actingAs($gestor)
            ->get('/admin/campos-personalizados')
            ->assertStatus(403);
    }

    public function test_ruta_admin_campos_403_para_supervisor(): void
    {
        $proyectoId = $this->proyectoId();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');

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
        $proyectoId = $this->proyectoId();
        $gestor = $this->crearConRol($proyectoId, 'GESTOR');
        $this->actingAs($gestor);

        Livewire::test(AdminCamposPersonalizados::class)
            ->assertStatus(403);
    }

    public function test_livewire_admin_campos_mount_aborta_con_supervisor(): void
    {
        $proyectoId = $this->proyectoId();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $this->actingAs($supervisor);

        Livewire::test(AdminCamposPersonalizados::class)
            ->assertStatus(403);
    }

    public function test_admin_global_crea_definicion_ok(): void
    {
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        $proyectoId = $this->proyectoId();
        $carteraId = (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->value('id');

        Livewire::test(AdminCamposPersonalizados::class)
            ->call('abrirFormCrear')
            ->set('form.proyecto_id', $proyectoId)
            ->set('form.ambito', 'caso')
            ->set('form.ambito_id', $carteraId)
            ->set('form.codigo', 'campo_admin_test')
            ->set('form.etiqueta', 'Campo Admin Test')
            ->set('form.tipo', 'texto_corto')
            ->set('form.obligatorio', false)
            ->set('form.activo', true)
            ->set('form.orden', 100)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('campos_personalizados', [
            'proyecto_id' => $proyectoId,
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
        $proyectoId = $this->proyectoId();
        $gestor = $this->crearConRol($proyectoId, 'GESTOR');

        $this->assertFalse($gestor->tienePermiso('campos.definir', $proyectoId));
        $this->assertFalse($gestor->tienePermiso('entidades.definir', $proyectoId));
    }

    public function test_supervisor_no_tiene_campos_definir(): void
    {
        $proyectoId = $this->proyectoId();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');

        $this->assertFalse($supervisor->tienePermiso('campos.definir', $proyectoId));
        $this->assertFalse($supervisor->tienePermiso('entidades.definir', $proyectoId));
    }

    private function proyectoId(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
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

    private function crearAdminGlobal(): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name' => 'Admin', 'email' => 'admin.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);
        $rolAdminId = (int) DB::table('roles')->where('codigo', 'ADMIN_GLOBAL')->value('id');
        DB::table('usuario_global_rol')->insert([
            'usuario_id' => $u->id, 'rol_id' => $rolAdminId,
        ]);

        return $u;
    }
}
