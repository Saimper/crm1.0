<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Modules\CamposPersonalizados\Infrastructure\Persistence\Models\CampoPersonalizadoModel;
use App\Modules\Tenancy\Domain\Exceptions\ConsultaSinContextoDeTenant;
use App\Modules\Tenancy\Infrastructure\Support\GuardiaDeContexto;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Tests\Support\EscenarioMultiMandante;
use Tests\TestCase;

/**
 * Fase 2: que una consulta sin contexto de tenant deje de devolverlo todo.
 *
 * El global scope nació fallando ABIERTO — sin binding, sin filtro — así que el
 * camino de error era también el camino sin aislamiento: bastaba con que la
 * resolución del proyecto fallara para consultar sobre todos los clientes.
 *
 * El modo estricto todavía no se enciende en producción: hay 5 comandos, 4 jobs,
 * 9 listeners y todas las pantallas /admin corriendo sin contexto. Estos tests
 * prueban el mecanismo para que encenderlo, cuando toque, sea un cambio de
 * configuración y no una apuesta.
 */
final class FalloCerradoDeScopeTest extends TestCase
{
    use EscenarioMultiMandante;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_sin_modo_estricto_la_consulta_sin_contexto_sigue_pasando(): void
    {
        $this->montarDosMandantes();
        Config::set('tenancy.scope_estricto', false);

        // Comportamiento heredado: devuelve de todos los proyectos. Se fija aquí
        // para que quede claro qué cambia al encender el modo estricto.
        $this->assertGreaterThan(0, CampoPersonalizadoModel::query()->count());
    }

    public function test_en_modo_estricto_consultar_sin_contexto_lanza(): void
    {
        $this->montarDosMandantes();
        Config::set('tenancy.scope_estricto', true);

        $this->expectException(ConsultaSinContextoDeTenant::class);

        CampoPersonalizadoModel::query()->count();
    }

    public function test_el_mensaje_dice_que_hacer(): void
    {
        $this->montarDosMandantes();
        Config::set('tenancy.scope_estricto', true);

        try {
            CampoPersonalizadoModel::query()->first();
            $this->fail('Debió lanzar ConsultaSinContextoDeTenant.');
        } catch (ConsultaSinContextoDeTenant $e) {
            $this->assertStringContainsString('sin proyecto activo', $e->getMessage());
            $this->assertStringContainsString('sinScopeProyecto', $e->getMessage());
        }
    }

    public function test_con_contexto_activo_el_modo_estricto_no_estorba(): void
    {
        $m = $this->montarDosMandantes();
        Config::set('tenancy.scope_estricto', true);

        app()->instance('tenancy.proyecto_activo', (object) ['id' => (int) $m['a']['proyecto']->id]);

        $ids = CampoPersonalizadoModel::query()->pluck('proyecto_id')->unique()->all();

        $this->assertSame([(int) $m['a']['proyecto']->id], array_values($ids));
    }

    public function test_saltarse_el_scope_a_proposito_sigue_siendo_legitimo(): void
    {
        $this->montarDosMandantes();
        Config::set('tenancy.scope_estricto', true);

        // Una consulta cross-tenant declarada como tal no es una fuga: es una
        // decisión escrita en el código.
        $total = CampoPersonalizadoModel::query()->sinScopeProyecto()->count();

        $this->assertGreaterThan(0, $total);
    }

    public function test_el_aviso_deja_constancia_del_origen(): void
    {
        $this->montarDosMandantes();
        Config::set('tenancy.scope_estricto', false);
        Config::set('tenancy.avisar_sin_contexto', true);

        Log::spy();

        CampoPersonalizadoModel::query()->count();

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $mensaje, array $ctx): bool => $mensaje === 'consulta sin contexto de tenant'
                && ($ctx['tipo'] ?? null) === 'proyecto'
                && is_string($ctx['origen'] ?? null));
    }

    public function test_sin_avisos_encendidos_no_se_ensucia_el_log(): void
    {
        $this->montarDosMandantes();
        Config::set('tenancy.scope_estricto', false);
        Config::set('tenancy.avisar_sin_contexto', false);

        Log::spy();

        CampoPersonalizadoModel::query()->count();

        Log::shouldNotHaveReceived('warning');
    }

    public function test_el_scope_de_mandante_se_cierra_igual_que_el_de_proyecto(): void
    {
        // Los dos tienen que cerrarse a la vez: si uno falla abierto, el
        // aislamiento queda a medias. El trait de mandante todavía no está
        // aplicado a ningún modelo, así que se ejercita el guardia directamente.
        Config::set('tenancy.scope_estricto', true);

        $this->expectException(ConsultaSinContextoDeTenant::class);

        GuardiaDeContexto::sinContexto('ModeloDePrueba', 'mandante');
    }

    /**
     * Guardia de regresión, no test de comportamiento: el proyecto activo
     * gobierna el global scope de 36 modelos, así que dejar que el cliente lo
     * elija por cabecera es dejarle elegir qué datos ve. Livewire re-aplica los
     * middleware persistentes sobre una petición cuyo REQUEST_URI es el path
     * original del snapshot firmado, de modo que el parámetro de ruta resuelve
     * solo y el respaldo por Referer nunca fue necesario.
     */
    public function test_el_middleware_no_lee_el_referer_para_elegir_proyecto(): void
    {
        $fuente = file_get_contents(
            app_path('Modules/Tenancy/Infrastructure/Http/Middleware/ResolverProyectoActivo.php')
        );

        $this->assertIsString($fuente);

        $codigo = preg_replace('#/\*\*.*?\*/#s', '', $fuente) ?? '';

        $this->assertStringNotContainsString(
            "headers->get('referer'",
            $codigo,
            'El proyecto activo no puede salir de una cabecera que controla el cliente.'
        );
        $this->assertStringNotContainsString('HTTP_REFERER', $codigo);
    }

    public function test_un_proyecto_ajeno_en_la_url_corta_con_403_y_no_deja_pasar(): void
    {
        $m = $this->montarDosMandantes();

        $this->actingAs($m['a']['gestor'])
            ->get('/proyectos/'.$m['b']['proyecto']->id.'/bandeja')
            ->assertStatus(403);

        $this->assertFalse(
            app()->bound('tenancy.proyecto_activo'),
            'Una petición cortada no puede dejar contexto publicado.'
        );
    }
}
