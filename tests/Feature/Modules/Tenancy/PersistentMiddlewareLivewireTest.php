<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tenancy;

use App\Modules\CamposPersonalizados\Infrastructure\Http\Livewire\FormularioCamposPersonalizados;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Fix reportado por usuario: al hacer Livewire update desde /proyectos/{id}/trabajo/...
 * el binding `tenancy.proyecto_activo` no estaba disponible porque el middleware
 * `proyecto.activo` no corre en la ruta `/livewire/update`.
 *
 * Lo que sigue vivo de aquel fix es lo que prueba el primer test: un componente que
 * recibe `proyectoId` como prop guarda aunque el binding de tenancy no exista.
 *
 * La otra mitad —resolver el proyecto desde el `Referer`— se retiró a propósito en
 * 40cd36c («cerrar el fallo abierto del aislamiento», Fase 2): el Referer lo pone el
 * cliente y el proyecto activo gobierna el global scope de 36 modelos. Los dos tests
 * que la cubrían quedan aquí como marca, saltados con la razón concreta; el contrato
 * de hoy lo fija `Tests\Feature\Tenancy\FalloCerradoDeScopeTest`.
 */
final class PersistentMiddlewareLivewireTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_gestor_guarda_campo_sin_tener_tenancy_bindeado_previamente(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $casoId = $this->crearCasoEn($proyecto, ['cartera' => $cartera]);

        // Un campo personalizado del ámbito caso×cartera, que es lo que el
        // componente descubre y persiste.
        DB::table('campos_personalizados')->insert([
            'proyecto_id' => $proyecto->id,
            'ambito' => 'caso',
            'ambito_id' => $cartera->id,
            'codigo' => 'operador_externo',
            'etiqueta' => 'Operador externo',
            'tipo' => 'texto_corto',
            'obligatorio' => false,
            'activo' => true,
            'orden' => 10,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        $gestor = $this->crearGestor($proyecto);
        $this->actingAs($gestor);

        // Simulamos lo que ocurre en el browser: el GET a /proyectos/{id}/trabajo renderiza
        // con tenancy bindeado, pero los subsiguientes POST /livewire/update NO pasan por
        // `proyecto.activo` como middleware de ruta. Al comenzar este test, el binding
        // NO existe — replica la condición real del bug.
        $this->assertFalse(app()->bound('tenancy.proyecto_activo'),
            'Precondición: tenancy.proyecto_activo NO debe estar bindeado al inicio.');

        // El componente se monta sin depender de app('tenancy.proyecto_activo') porque
        // recibe proyectoId como prop. Debe poder guardar incluso sin binding previo.
        Livewire::test(FormularioCamposPersonalizados::class, [
            'proyectoId' => (int) $proyecto->id,
            'ambito' => 'caso',
            'ambitoId' => (int) $cartera->id,
            'entidadId' => $casoId,
        ])
            ->set('valores.operador_externo', 'Valor del gestor')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('valores_campo_personalizado', [
            'entidad_id' => $casoId,
            'valor_texto_corto' => 'Valor del gestor',
        ]);
    }

    public function test_middleware_extrae_proyecto_del_referer(): void
    {
        $this->markTestSkipped(
            'El respaldo por Referer se eliminó a propósito en 40cd36c: el proyecto activo '
            .'sale sólo de {proyecto_id} de la ruta, porque el Referer lo controla el cliente '
            .'y el proyecto gobierna el global scope. El contrato inverso lo fija '
            .'FalloCerradoDeScopeTest::test_el_middleware_no_lee_el_referer_para_elegir_proyecto.'
        );
    }

    public function test_middleware_no_aborta_en_livewire_cuando_no_hay_referer_resolvible(): void
    {
        $this->markTestSkipped(
            'El middleware ya no deja pasar sin proyecto: desde 40cd36c corta con abort(404) '
            .'en vez de seguir sin contexto, porque el global scope falla abierto y el camino '
            .'de error era el camino sin aislamiento. Cubierto hoy por '
            .'FalloCerradoDeScopeTest::test_un_proyecto_ajeno_en_la_url_corta_con_403_y_no_deja_pasar.'
        );
    }
}
