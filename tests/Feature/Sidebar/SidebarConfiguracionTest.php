<?php

declare(strict_types=1);

namespace Tests\Feature\Sidebar;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Regresión de sidebar tras la consolidación F36 P8.
 *
 * El wizard "Configurar proyecto" absorbe los flujos de definición de
 * Carteras, Catálogos comunes (estados/tipos gestión/resultados/motivos),
 * Catálogos tipo-específicos y Campos personalizados. Los links directos
 * del sidebar de proyecto a esas rutas se eliminan; solo queda
 * "Configurar proyecto" como puerta de entrada para ADMIN_GLOBAL.
 */
final class SidebarConfiguracionTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_global_ve_solo_configurar_proyecto_y_no_carteras_directo(): void
    {
        [$proyecto, $admin] = $this->escenarioAdmin();

        $resp = $this->actingAs($admin)
            ->get(route('proyectos.casos.lista', ['proyecto_id' => $proyecto->id]));

        $resp->assertStatus(200);
        $resp->assertSee(
            route('admin.proyectos.configurar.editar', ['proyecto' => $proyecto->public_id]),
            false,
        );
        $resp->assertDontSee(
            '/proyectos/'.$proyecto->id.'/carteras',
            false,
        );
    }

    public function test_admin_global_ve_solo_configurar_proyecto_y_no_estados_caso_directo(): void
    {
        // Estados de caso vivían dentro de `proyectos.catalogos` (un único link
        // que abría todos los catálogos comunes). Esa entrada fue eliminada.
        [$proyecto, $admin] = $this->escenarioAdmin();

        $resp = $this->actingAs($admin)
            ->get(route('proyectos.casos.lista', ['proyecto_id' => $proyecto->id]));

        $resp->assertStatus(200);
        $resp->assertDontSee(
            '/proyectos/'.$proyecto->id.'/catalogos',
            false,
        );
    }

    public function test_admin_global_ve_solo_configurar_proyecto_y_no_tipos_gestion_directo(): void
    {
        // Tipos de gestión también vivían dentro de `proyectos.catalogos`.
        [$proyecto, $admin] = $this->escenarioAdmin();

        $resp = $this->actingAs($admin)
            ->get(route('proyectos.casos.lista', ['proyecto_id' => $proyecto->id]));

        $resp->assertStatus(200);
        $resp->assertDontSee(
            '/proyectos/'.$proyecto->id.'/catalogos',
            false,
        );
    }

    public function test_admin_global_ve_solo_configurar_proyecto_y_no_motivos_directo(): void
    {
        // Motivos no-contacto también vivían dentro de `proyectos.catalogos`.
        [$proyecto, $admin] = $this->escenarioAdmin();

        $resp = $this->actingAs($admin)
            ->get(route('proyectos.casos.lista', ['proyecto_id' => $proyecto->id]));

        $resp->assertStatus(200);
        $resp->assertDontSee(
            '/proyectos/'.$proyecto->id.'/catalogos',
            false,
        );
    }

    public function test_admin_global_ve_solo_configurar_proyecto_y_no_catalogos_tipo_directo(): void
    {
        // Los catálogos tipo-específicos (tramos mora, productos venta, etc.)
        // se servían bajo el mismo `proyectos.catalogos` con tabs internos.
        [$proyecto, $admin] = $this->escenarioAdmin();

        $resp = $this->actingAs($admin)
            ->get(route('proyectos.casos.lista', ['proyecto_id' => $proyecto->id]));

        $resp->assertStatus(200);
        $resp->assertDontSee(
            '/proyectos/'.$proyecto->id.'/catalogos',
            false,
        );
    }

    public function test_admin_global_ve_solo_configurar_proyecto_y_no_campos_personalizados_directo(): void
    {
        // Campos personalizados (definición) nunca tuvo link directo en el
        // sidebar de proyecto — solo en admin global cross-project. La única
        // puerta de entrada del proyecto al flujo de definición es ahora el
        // wizard. Verificamos que el link al wizard sí está y que no se
        // colaron links a `proyectos.catalogos` (donde antes se mezclaban
        // las definiciones).
        [$proyecto, $admin] = $this->escenarioAdmin();

        $resp = $this->actingAs($admin)
            ->get(route('proyectos.casos.lista', ['proyecto_id' => $proyecto->id]));

        $resp->assertStatus(200);
        $resp->assertSee(
            route('admin.proyectos.configurar.editar', ['proyecto' => $proyecto->public_id]),
            false,
        );
        $resp->assertDontSee(
            '/proyectos/'.$proyecto->id.'/catalogos',
            false,
        );
    }

    public function test_supervisor_no_ve_configurar_proyecto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);

        $resp = $this->actingAs($supervisor)
            ->get(route('proyectos.casos.lista', ['proyecto_id' => $proyecto->id]));

        $resp->assertStatus(200);
        $resp->assertDontSee(
            route('admin.proyectos.configurar', ['proyecto' => $proyecto->public_id]),
            false,
        );
        $resp->assertDontSee(
            route('admin.proyectos.configurar.editar', ['proyecto' => $proyecto->public_id]),
            false,
        );
    }

    public function test_gestor_no_ve_configurar_proyecto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        $resp = $this->actingAs($gestor)
            ->get(route('proyectos.casos.lista', ['proyecto_id' => $proyecto->id]));

        $resp->assertStatus(200);
        $resp->assertDontSee(
            route('admin.proyectos.configurar', ['proyecto' => $proyecto->public_id]),
            false,
        );
        $resp->assertDontSee(
            route('admin.proyectos.configurar.editar', ['proyecto' => $proyecto->public_id]),
            false,
        );
    }

    public function test_auditor_no_ve_configurar_proyecto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $auditor = $this->crearAuditor($proyecto);

        $resp = $this->actingAs($auditor)
            ->get(route('proyectos.casos.lista', ['proyecto_id' => $proyecto->id]));

        $resp->assertStatus(200);
        $resp->assertDontSee(
            route('admin.proyectos.configurar', ['proyecto' => $proyecto->public_id]),
            false,
        );
        $resp->assertDontSee(
            route('admin.proyectos.configurar.editar', ['proyecto' => $proyecto->public_id]),
            false,
        );
    }

    public function test_sidebar_admin_global_conserva_mandantes_proyectos_usuarios(): void
    {
        $admin = $this->crearAdminGlobal();

        $resp = $this->actingAs($admin)->get(route('admin.dashboard'));

        $resp->assertStatus(200);
        $resp->assertSee(route('admin.mandantes'), false);
        $resp->assertSee(route('admin.proyectos'), false);
        $resp->assertSee(route('admin.usuarios'), false);
        $resp->assertSee(route('admin.integracion.secrets'), false);
        $resp->assertSee(route('admin.auditoria'), false);
    }

    /**
     * @return array{0: \stdClass, 1: User}
     */
    private function escenarioAdmin(): array
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->completarObligatoriosParaQueAvanceCompleto($proyecto);
        $admin = $this->crearAdminGlobal();

        return [$proyecto, $admin];
    }

    private function completarObligatoriosParaQueAvanceCompleto(\stdClass $proyecto): void
    {
        // Forzamos avance completo para que la entrada del sidebar apunte al
        // modo edición (admin.proyectos.configurar.editar). Los tests con
        // proyecto incompleto se cubren en P7.
        $proyectoId = (int) $proyecto->id;
        $now = Carbon::now();

        $this->crearCarteraEn($proyecto);
        $this->crearEstadoCasoEn($proyecto);

        DB::table('tipos_gestion')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'TG', 'nombre' => 'Tipo', 'orden' => 100,
            'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
        DB::table('resultados')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'R', 'nombre' => 'Resultado', 'orden' => 100,
            'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
        DB::table('motivos_no_contacto')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'M', 'nombre' => 'Motivo', 'orden' => 100,
            'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
        DB::table('tramos_mora')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'TM', 'nombre' => 'Tramo', 'dias_desde' => 0,
            'orden' => 100, 'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
        DB::table('tipos_pago')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'TP', 'nombre' => 'Tipo pago', 'orden' => 100,
            'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
    }
}
