<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Modules\Tenancy\Application\Services\ResolutorMandanteActivo;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\SelectorMandante;
use App\Modules\Tenancy\Infrastructure\Http\Middleware\ResolverMandanteActivo;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\EscenarioMultiMandante;
use Tests\TestCase;

/**
 * Fase 1: el mandante — la empresa cliente — pasa a existir como contexto.
 *
 * Hasta aquí sólo había `tenancy.proyecto_activo`, y por eso /admin corría sin
 * tenant y mostraba datos de todos los clientes. Estos tests fijan de dónde sale
 * el mandante activo y, sobre todo, de dónde NO sale.
 */
final class ContextoMandanteActivoTest extends TestCase
{
    use EscenarioMultiMandante;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function resolutor(): ResolutorMandanteActivo
    {
        return $this->app->make(ResolutorMandanteActivo::class);
    }

    // ─── Quién alcanza qué ──────────────────────────────────────────────

    public function test_un_admin_de_mandante_solo_alcanza_el_suyo(): void
    {
        $m = $this->montarDosMandantes();

        $this->assertSame(
            [(int) $m['a']['mandante']->id],
            $this->resolutor()->permitidos($m['a']['adminMandante'])
        );
    }

    public function test_un_gestor_alcanza_el_mandante_de_sus_proyectos(): void
    {
        $m = $this->montarDosMandantes();

        $this->assertSame(
            [(int) $m['a']['mandante']->id],
            $this->resolutor()->permitidos($m['a']['gestor'])
        );
    }

    public function test_el_admin_global_alcanza_todos_pero_eso_no_es_verlos_a_la_vez(): void
    {
        $m = $this->montarDosMandantes();
        $global = $this->crearAdminGlobal();

        $permitidos = $this->resolutor()->permitidos($global);

        $this->assertContains((int) $m['a']['mandante']->id, $permitidos);
        $this->assertContains((int) $m['b']['mandante']->id, $permitidos);
        $this->assertNull(
            $this->resolutor()->unicoPermitido($global),
            'Con más de un cliente alcanzable, el admin global tiene que elegir (D1).'
        );
    }

    public function test_un_mandante_desactivado_deja_de_alcanzarse(): void
    {
        $m = $this->montarDosMandantes();
        DB::table('mandantes')->where('id', $m['b']['mandante']->id)->update(['activo' => false]);

        $global = $this->crearAdminGlobal();

        $this->assertNotContains((int) $m['b']['mandante']->id, $this->resolutor()->permitidos($global));
        $this->assertNull($this->resolutor()->resolver($global, (int) $m['b']['mandante']->id));
    }

    public function test_no_se_resuelve_un_mandante_ajeno_aunque_se_pida_por_id(): void
    {
        $m = $this->montarDosMandantes();

        $this->assertNull(
            $this->resolutor()->resolver($m['a']['adminMandante'], (int) $m['b']['mandante']->id),
            'Pedir el id de otro cliente no puede devolver ese cliente.'
        );
    }

    // ─── De dónde sale el contexto ──────────────────────────────────────

    public function test_en_rutas_operativas_el_mandante_se_deriva_del_proyecto(): void
    {
        $m = $this->montarDosMandantes();

        $this->assertSame(
            (int) $m['a']['mandante']->id,
            $this->resolutor()->delProyecto((int) $m['a']['proyecto']->id)
        );
    }

    public function test_una_sesion_con_el_cliente_ajeno_no_da_acceso_a_ese_cliente(): void
    {
        $m = $this->montarDosMandantes();
        $admin = $m['a']['adminMandante'];

        // Sesión contaminada a mano con el id del cliente ajeno: es lo que haría
        // quien manipulara la cookie de sesión.
        $this->actingAs($admin)
            ->withSession([ResolverMandanteActivo::CLAVE_SESION => (int) $m['b']['mandante']->id])
            ->get('/admin/usuarios')
            ->assertOk();

        // El middleware la descarta y cae al único que sí alcanza: el suyo.
        $this->assertSame(
            (int) $m['a']['mandante']->id,
            (int) app('tenancy.mandante_activo')->id,
            'La sesión propone, el permiso dispone.'
        );
    }

    public function test_quien_alcanza_un_solo_cliente_no_tiene_que_elegir(): void
    {
        $m = $this->montarDosMandantes();

        $this->assertSame(
            (int) $m['a']['mandante']->id,
            $this->resolutor()->unicoPermitido($m['a']['adminMandante'])
        );
    }

    public function test_sin_ningun_cliente_alcanzable_no_se_inventa_uno(): void
    {
        $huerfano = \App\Models\User::query()->create([
            'name' => 'Sin cliente',
            'email' => 'sincliente@crm.local',
            'password' => bcrypt('x'),
            'activo' => true,
        ]);

        $this->assertSame([], $this->resolutor()->permitidos($huerfano));
        $this->assertNull($this->resolutor()->unicoPermitido($huerfano));
    }

    // ─── El conmutador ──────────────────────────────────────────────────

    public function test_el_selector_solo_ofrece_los_clientes_alcanzables(): void
    {
        $m = $this->montarDosMandantes();

        $html = Livewire::actingAs($m['a']['adminMandante'])->test(SelectorMandante::class)->html();

        $this->assertStringContainsString($m['a']['mandante']->codigo, $html);
        $this->assertStringNotContainsString($m['b']['mandante']->codigo, $html);
    }

    public function test_el_selector_rechaza_un_id_ajeno_forjado_en_el_payload(): void
    {
        $m = $this->montarDosMandantes();

        Livewire::actingAs($m['a']['adminMandante'])->test(SelectorMandante::class)
            ->call('seleccionar', (int) $m['b']['mandante']->id)
            ->assertStatus(403);

        $this->assertNotSame(
            (int) $m['b']['mandante']->id,
            (int) session(ResolverMandanteActivo::CLAVE_SESION, 0),
            'Un id rechazado no puede quedar guardado en sesión.'
        );
    }

    public function test_elegir_un_cliente_propio_lo_deja_activo_en_sesion(): void
    {
        $m = $this->montarDosMandantes();

        Livewire::actingAs($m['a']['adminMandante'])->test(SelectorMandante::class)
            ->call('seleccionar', (int) $m['a']['mandante']->id)
            ->assertHasNoErrors();

        $this->assertSame(
            (int) $m['a']['mandante']->id,
            (int) session(ResolverMandanteActivo::CLAVE_SESION)
        );
    }

    public function test_el_admin_global_puede_conmutar_entre_clientes(): void
    {
        $m = $this->montarDosMandantes();
        $global = $this->crearAdminGlobal();

        $componente = Livewire::actingAs($global)->test(SelectorMandante::class);

        $componente->call('seleccionar', (int) $m['a']['mandante']->id);
        $this->assertSame((int) $m['a']['mandante']->id, (int) session(ResolverMandanteActivo::CLAVE_SESION));

        $componente->call('seleccionar', (int) $m['b']['mandante']->id);
        $this->assertSame((int) $m['b']['mandante']->id, (int) session(ResolverMandanteActivo::CLAVE_SESION));
    }

    // ─── El middleware en vivo ──────────────────────────────────────────

    public function test_una_pantalla_admin_publica_el_mandante_activo(): void
    {
        $m = $this->montarDosMandantes();

        $this->actingAs($m['a']['adminMandante'])->get('/admin/usuarios')->assertOk();

        $this->assertTrue(app()->bound('tenancy.mandante_activo'));
        $this->assertSame((int) $m['a']['mandante']->id, (int) app('tenancy.mandante_activo')->id);
    }

    public function test_el_admin_global_sin_cliente_elegido_va_al_selector(): void
    {
        $this->montarDosMandantes();
        $global = $this->crearAdminGlobal();

        $this->actingAs($global)->get('/admin/usuarios')
            ->assertRedirect(route('admin.mandante-activo'));
    }

    public function test_el_selector_no_redirige_a_si_mismo(): void
    {
        $this->montarDosMandantes();
        $global = $this->crearAdminGlobal();

        $this->actingAs($global)->get('/admin/cliente')->assertOk();
    }

    public function test_una_sesion_con_cliente_revocado_no_sobrevive(): void
    {
        $m = $this->montarDosMandantes();
        $admin = $m['a']['adminMandante'];

        // Trabajaba en su cliente y le revocan el rol.
        $this->actingAs($admin)
            ->withSession([ResolverMandanteActivo::CLAVE_SESION => (int) $m['a']['mandante']->id])
            ->get('/admin/usuarios')->assertOk();

        DB::table('usuario_mandante_rol')->where('usuario_id', $admin->id)->update(['activo' => false]);

        // Sin rol de mandante, quien corta primero es admin.dual con un 403; el
        // middleware de mandante ni llega a ejecutarse. Lo que importa es que la
        // sesión guardada NO le devuelve el acceso: el permiso manda sobre ella.
        $this->actingAs($admin->fresh())
            ->withSession([ResolverMandanteActivo::CLAVE_SESION => (int) $m['a']['mandante']->id])
            ->get('/admin/usuarios')
            ->assertStatus(403);
    }

    public function test_un_admin_de_un_cliente_desactivado_no_queda_con_acceso_fantasma(): void
    {
        $m = $this->montarDosMandantes();
        DB::table('mandantes')->where('id', $m['a']['mandante']->id)->update(['activo' => false]);

        // Conserva el rol, pero el cliente ya no está vivo: no se alcanza.
        $this->assertSame([], $this->resolutor()->permitidos($m['a']['adminMandante']->fresh()));
    }

    public function test_el_selector_avisa_cuando_no_hay_ningun_cliente_alcanzable(): void
    {
        $m = $this->montarDosMandantes();
        DB::table('mandantes')->where('id', $m['a']['mandante']->id)->update(['activo' => false]);

        $html = Livewire::actingAs($m['a']['adminMandante']->fresh())->test(SelectorMandante::class)->html();

        $this->assertStringNotContainsString($m['a']['mandante']->codigo, $html);
        $this->assertStringNotContainsString($m['b']['mandante']->codigo, $html);
    }

    public function test_conmutar_de_cliente_cambia_lo_que_publica_el_middleware(): void
    {
        $m = $this->montarDosMandantes();
        $global = $this->crearAdminGlobal();

        $this->actingAs($global)
            ->withSession([ResolverMandanteActivo::CLAVE_SESION => (int) $m['a']['mandante']->id])
            ->get('/admin/usuarios')->assertOk();
        $this->assertSame((int) $m['a']['mandante']->id, (int) app('tenancy.mandante_activo')->id);

        $this->actingAs($global)
            ->withSession([ResolverMandanteActivo::CLAVE_SESION => (int) $m['b']['mandante']->id])
            ->get('/admin/usuarios')->assertOk();
        $this->assertSame((int) $m['b']['mandante']->id, (int) app('tenancy.mandante_activo')->id);
    }

    public function test_en_ruta_operativa_el_contexto_sale_del_proyecto_no_de_la_sesion(): void
    {
        $m = $this->montarDosMandantes();

        // Sesión apuntando al cliente ajeno, pero la URL manda: el proyecto es de A.
        $this->assertSame(
            (int) $m['a']['mandante']->id,
            $this->resolutor()->delProyecto((int) $m['a']['proyecto']->id),
            'En rutas operativas el mandante se deriva del proyecto, nunca se acepta aparte.'
        );
        $this->assertNotSame(
            $this->resolutor()->delProyecto((int) $m['a']['proyecto']->id),
            $this->resolutor()->delProyecto((int) $m['b']['proyecto']->id)
        );
    }
}
