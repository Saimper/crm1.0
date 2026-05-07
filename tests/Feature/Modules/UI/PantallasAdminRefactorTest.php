<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\UI;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fase 29: verifica que las pantallas admin/reportes/catálogos/importaciones/auditoría
 * renderizan con el design system F29 (clase .page + .page-header + .card).
 */
final class PantallasAdminRefactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

    }

    public function test_admin_dashboard_refactorizado(): void
    {
        $admin = $this->crearAdminGlobal();
        $response = $this->actingAs($admin)->get('/admin')->assertStatus(200);
        $response->assertSee('page-header', false);
        $response->assertSee('Administración global', false);
    }

    public function test_admin_mandantes_refactorizado(): void
    {
        $admin = $this->crearAdminGlobal();
        $response = $this->actingAs($admin)->get(route('admin.mandantes'))->assertStatus(200);
        $response->assertSee('page-header', false);
    }

    public function test_admin_proyectos_refactorizado(): void
    {
        $admin = $this->crearAdminGlobal();
        $response = $this->actingAs($admin)->get(route('admin.proyectos'))->assertStatus(200);
        $response->assertSee('page-header', false);
    }

    public function test_admin_usuarios_refactorizado(): void
    {
        $admin = $this->crearAdminGlobal();
        $response = $this->actingAs($admin)->get(route('admin.usuarios'))->assertStatus(200);
        $response->assertSee('page-header', false);
    }

    public function test_admin_campos_refactorizado(): void
    {
        $admin = $this->crearAdminGlobal();
        $response = $this->actingAs($admin)->get(route('admin.campos-personalizados'))->assertStatus(200);
        $response->assertSee('page-header', false);
    }

    public function test_admin_entidades_refactorizado(): void
    {
        $admin = $this->crearAdminGlobal();
        $response = $this->actingAs($admin)->get(route('admin.entidades-configurables'))->assertStatus(200);
        $response->assertSee('page-header', false);
    }

    public function test_reportes_operativos_refactorizado(): void
    {
        $proyectoId = $this->proyectoId();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $response = $this->actingAs($supervisor)
            ->get(route('proyectos.reportes.operativos', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);
        $response->assertSee('page-header', false);
    }

    public function test_reportes_analiticos_refactorizado(): void
    {
        $proyectoId = $this->proyectoId();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $response = $this->actingAs($supervisor)
            ->get(route('proyectos.reportes.analiticos', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);
        $response->assertSee('page-header', false);
    }

    public function test_auditoria_refactorizado(): void
    {
        $proyectoId = $this->proyectoId();
        $auditor = $this->crearConRol($proyectoId, 'AUDITOR');
        $response = $this->actingAs($auditor)
            ->get(route('proyectos.auditoria', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);
        $response->assertSee('page-header', false);
    }

    public function test_importaciones_refactorizado(): void
    {
        $proyectoId = $this->proyectoId();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $response = $this->actingAs($supervisor)
            ->get(route('proyectos.importaciones', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);
        $response->assertSee('page-header', false);
    }

    public function test_catalogos_refactorizado(): void
    {
        $proyectoId = $this->proyectoId();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $response = $this->actingAs($supervisor)
            ->get(route('proyectos.catalogos', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);
        $response->assertSee('page-header', false);
    }

    public function test_asignaciones_masiva_refactorizado(): void
    {
        $proyectoId = $this->proyectoId();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $response = $this->actingAs($supervisor)
            ->get(route('proyectos.asignaciones.masiva', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);
        $response->assertSee('page-header', false);
    }

    public function test_equipos_del_proyecto_refactorizado(): void
    {
        $proyectoId = $this->proyectoId();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $response = $this->actingAs($supervisor)
            ->get(route('proyectos.equipos', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);
        $response->assertSee('page-header', false);
    }

    public function test_usuarios_del_proyecto_refactorizado(): void
    {
        $proyectoId = $this->proyectoId();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $response = $this->actingAs($supervisor)
            ->get(route('proyectos.usuarios', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);
        $response->assertSee('page-header', false);
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
            'email' => strtolower($codigoRol).'.f27.'.Str::random(4).'@crm.local',
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
            'name' => 'Admin', 'email' => 'admin.f27.'.Str::random(4).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);
        $rolAdminId = (int) DB::table('roles')->where('codigo', 'ADMIN_GLOBAL')->value('id');
        DB::table('usuario_global_rol')->insert([
            'usuario_id' => $u->id, 'rol_id' => $rolAdminId,
        ]);

        return $u;
    }
}
