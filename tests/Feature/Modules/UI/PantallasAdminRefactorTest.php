<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\UI;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Fase 29: verifica que las pantallas admin/reportes/catálogos/importaciones/auditoría
 * renderizan con el design system F29 (clase .page + .page-header + .card).
 */
final class PantallasAdminRefactorTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    private ?stdClass $proyecto = null;

    private ?stdClass $mandante = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_dashboard_refactorizado(): void
    {
        $admin = $this->adminGlobalEnUnCliente();
        $response = $this->actingAs($admin)->get('/admin')->assertStatus(200);
        $response->assertSee('page-header', false);

        // El rótulo era «Administración global» cuando el panel corría sin
        // cliente. Desde que /admin exige mandante activo, el mismo dashboard
        // se titula «Administración · <cliente>» y declara el rol en el
        // subtítulo. Se comprueba lo mismo que antes —que un ADMIN_GLOBAL
        // aterriza en el panel de administración y el panel lo dice— con el
        // texto que la pantalla usa hoy.
        $response->assertSee('Administración · '.$this->mandante->nombre, false);
        $response->assertSee('ADMIN_GLOBAL', false);
    }

    public function test_admin_mandantes_refactorizado(): void
    {
        $admin = $this->adminGlobalEnUnCliente();
        $response = $this->actingAs($admin)->get(route('admin.mandantes'))->assertStatus(200);
        $response->assertSee('page-header', false);
    }

    public function test_admin_proyectos_refactorizado(): void
    {
        $admin = $this->adminGlobalEnUnCliente();
        $response = $this->actingAs($admin)->get(route('admin.proyectos'))->assertStatus(200);
        $response->assertSee('page-header', false);
    }

    public function test_admin_usuarios_refactorizado(): void
    {
        $admin = $this->adminGlobalEnUnCliente();
        $response = $this->actingAs($admin)->get(route('admin.usuarios'))->assertStatus(200);
        $response->assertSee('page-header', false);
    }

    public function test_admin_campos_refactorizado(): void
    {
        $admin = $this->adminGlobalEnUnCliente();
        $response = $this->actingAs($admin)->get(route('admin.campos-personalizados'))->assertStatus(200);
        $response->assertSee('page-header', false);
    }

    public function test_admin_entidades_refactorizado(): void
    {
        $admin = $this->adminGlobalEnUnCliente();
        $response = $this->actingAs($admin)->get(route('admin.entidades-configurables'))->assertStatus(200);
        $response->assertSee('page-header', false);
    }

    public function test_reportes_operativos_refactorizado(): void
    {
        $proyectoId = $this->proyectoId();
        $supervisor = $this->crearSupervisor($this->proyecto());
        $response = $this->actingAs($supervisor)
            ->get(route('proyectos.reportes.operativos', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);
        $response->assertSee('page-header', false);
    }

    public function test_reportes_analiticos_refactorizado(): void
    {
        $proyectoId = $this->proyectoId();
        $supervisor = $this->crearSupervisor($this->proyecto());
        $response = $this->actingAs($supervisor)
            ->get(route('proyectos.reportes.analiticos', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);
        $response->assertSee('page-header', false);
    }

    public function test_auditoria_refactorizado(): void
    {
        $proyectoId = $this->proyectoId();
        $auditor = $this->crearAuditor($this->proyecto());
        $response = $this->actingAs($auditor)
            ->get(route('proyectos.auditoria', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);
        $response->assertSee('page-header', false);
    }

    public function test_importaciones_refactorizado(): void
    {
        $proyectoId = $this->proyectoId();
        $supervisor = $this->crearSupervisor($this->proyecto());
        $response = $this->actingAs($supervisor)
            ->get(route('proyectos.importaciones', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);
        $response->assertSee('page-header', false);
    }

    // test_catalogos_refactorizado eliminado en F36 P9: la ruta proyectos.catalogos
    // fue removida; el flujo se centralizó en el wizard "Configurar proyecto".

    public function test_asignaciones_masiva_refactorizado(): void
    {
        $proyectoId = $this->proyectoId();
        $supervisor = $this->crearSupervisor($this->proyecto());
        $response = $this->actingAs($supervisor)
            ->get(route('proyectos.asignaciones.masiva', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);
        $response->assertSee('page-header', false);
    }

    public function test_equipos_del_proyecto_refactorizado(): void
    {
        $proyectoId = $this->proyectoId();
        $supervisor = $this->crearSupervisor($this->proyecto());
        $response = $this->actingAs($supervisor)
            ->get(route('proyectos.equipos', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);
        $response->assertSee('page-header', false);
    }

    public function test_usuarios_del_proyecto_refactorizado(): void
    {
        $proyectoId = $this->proyectoId();
        $supervisor = $this->crearSupervisor($this->proyecto());
        $response = $this->actingAs($supervisor)
            ->get(route('proyectos.usuarios', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);
        $response->assertSee('page-header', false);
    }

    /**
     * Un ADMIN_GLOBAL y exactamente un mandante vivo.
     *
     * El middleware `mandante.activo` de /admin exige cliente activo y, si no
     * puede resolver uno, redirige al selector (302). Con un único mandante
     * alcanzable lo resuelve solo, que es lo que antes daba el seeder demo con
     * su mandante `BPO_DEMO`.
     */
    private function adminGlobalEnUnCliente(): User
    {
        $this->mandante ??= $this->crearMandante();

        return $this->crearAdminGlobal();
    }

    /**
     * El proyecto del escenario: antes lo aportaba el seeder demo
     * (`COBRANZA_DEMO_2026`), ahora lo monta el test con una cartera y un caso
     * para que las pantallas de reportes y asignaciones tengan algo que pintar.
     */
    private function proyecto(): stdClass
    {
        if ($this->proyecto === null) {
            $this->proyecto = $this->crearProyectoCobranza();
            $cartera = $this->crearCarteraEn($this->proyecto, 'CONSUMO');
            $this->crearCasoEn($this->proyecto, ['cartera' => $cartera]);
        }

        return $this->proyecto;
    }

    private function proyectoId(): int
    {
        return (int) $this->proyecto()->id;
    }
}
