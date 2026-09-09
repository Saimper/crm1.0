<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy\ConfiguradorPasos;

use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\PasoCarteras;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioMultiMandante;
use Tests\TestCase;

/**
 * El `can:proyectos.configurar` de /admin/proyectos/{proyecto}/configurar
 * protege la PÁGINA. Cada acción de este paso es un POST aparte a
 * /livewire/update que vuelve a entrar en el componente sin pasar por ese
 * middleware, con las propiedades que mande el cliente.
 *
 * De ahí las dos comprobaciones distintas que se afirman aquí: que el usuario
 * PUEDA configurar (permiso, 403) y que la cartera SEA del proyecto que la
 * ruta fijó (pertenencia, 404). No hay ningún permiso `carteras.*` en el
 * seeder: `proyectos.configurar` es el único que cubre esta pantalla, y lo
 * tienen ADMIN_MANDANTE sobre los proyectos de su mandante y ADMIN_GLOBAL vía
 * Gate::before.
 */
final class PasoCarterasAutorizacionTest extends TestCase
{
    use EscenarioMultiMandante;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    // -----------------------------------------------------------------
    // (a) quien NO debe poder
    // -----------------------------------------------------------------

    public function test_supervisor_no_puede_montar_el_paso(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->actingAs($this->crearSupervisor($proyecto));

        Livewire::test(PasoCarteras::class, ['proyecto' => $this->modelo($proyecto)])
            ->assertForbidden();
    }

    /**
     * El commit vuelve a entrar en el componente SIN pasar por el `can:` de la
     * ruta: se monta legítimamente y el POST siguiente llega con otro actor
     * (sesión cambiada, permiso revocado, petición fabricada). Por eso la
     * comprobación tiene que estar en el método que escribe, no sólo en mount.
     */
    public function test_supervisor_no_puede_crear_una_cartera_en_un_componente_ya_montado(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);

        $this->actingAs($this->crearAdminDeMandante($mandante, 'propio'));
        $componente = Livewire::test(PasoCarteras::class, ['proyecto' => $this->modelo($proyecto)])
            ->call('abrirFormCrear');

        $this->actingAs($this->crearSupervisor($proyecto));

        $componente
            ->set('form.codigo', 'INTRUSA')
            ->set('form.nombre', 'Intrusa')
            ->call('guardarCartera')
            ->assertForbidden();

        $this->assertDatabaseMissing('carteras', [
            'proyecto_id' => $proyecto->id,
            'codigo' => 'INTRUSA',
        ]);
    }

    public function test_supervisor_no_puede_eliminar_una_cartera_en_un_componente_ya_montado(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $cartera = $this->crearCarteraEn($proyecto);

        $this->actingAs($this->crearAdminDeMandante($mandante, 'propio'));
        $componente = Livewire::test(PasoCarteras::class, ['proyecto' => $this->modelo($proyecto)]);

        $this->actingAs($this->crearSupervisor($proyecto));

        $componente
            ->call('eliminarCartera', (int) $cartera->id)
            ->assertForbidden();

        $this->assertNull(
            DB::table('carteras')->where('id', $cartera->id)->value('eliminada_en'),
            'SUPERVISOR no tiene proyectos.configurar: la cartera no debe haberse borrado.'
        );
    }

    public function test_gestor_no_puede_alternar_el_estado_en_un_componente_ya_montado(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $cartera = $this->crearCarteraEn($proyecto);

        $this->actingAs($this->crearAdminDeMandante($mandante, 'propio'));
        $componente = Livewire::test(PasoCarteras::class, ['proyecto' => $this->modelo($proyecto)]);

        $this->actingAs($this->crearGestor($proyecto));

        $componente
            ->call('toggleActivo', (int) $cartera->id)
            ->assertForbidden();

        $this->assertSame(
            1,
            (int) DB::table('carteras')->where('id', $cartera->id)->value('activo'),
        );
    }

    // -----------------------------------------------------------------
    // (b) quien SÍ debe poder — el camino legítimo sigue vivo
    // -----------------------------------------------------------------

    public function test_admin_de_mandante_si_puede_crear_y_alternar_en_su_proyecto(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $this->actingAs($this->crearAdminDeMandante($mandante, 'propio'));

        Livewire::test(PasoCarteras::class, ['proyecto' => $this->modelo($proyecto)])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'LEGITIMA')
            ->set('form.nombre', 'Legítima')
            ->call('guardarCartera')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('carteras', [
            'proyecto_id' => $proyecto->id,
            'codigo' => 'LEGITIMA',
            'activo' => true,
        ]);

        $id = (int) DB::table('carteras')
            ->where('proyecto_id', $proyecto->id)
            ->where('codigo', 'LEGITIMA')
            ->value('id');

        Livewire::test(PasoCarteras::class, ['proyecto' => $this->modelo($proyecto)])
            ->call('toggleActivo', $id);

        $this->assertSame(
            0,
            (int) DB::table('carteras')->where('id', $id)->value('activo'),
            'El endurecimiento no puede romper la operación de quien sí tiene el permiso.'
        );
    }

    public function test_admin_de_mandante_si_puede_eliminar_una_cartera_sin_casos(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $cartera = $this->crearCarteraEn($proyecto);
        $this->actingAs($this->crearAdminDeMandante($mandante, 'propio'));

        Livewire::test(PasoCarteras::class, ['proyecto' => $this->modelo($proyecto)])
            ->call('eliminarCartera', (int) $cartera->id);

        $this->assertNotNull(
            DB::table('carteras')->where('id', $cartera->id)->value('eliminada_en'),
        );
    }

    // -----------------------------------------------------------------
    // Pertenencia: el permiso es por proyecto, la fila también
    // -----------------------------------------------------------------

    public function test_no_se_puede_eliminar_la_cartera_de_otro_mandante(): void
    {
        $propio = $this->crearMandante('MND_PROPIO');
        $ajeno = $this->crearMandante('MND_AJENO');
        $proyectoPropio = $this->crearProyectoCobranza($propio);
        $proyectoAjeno = $this->crearProyectoCobranza($ajeno);
        $carteraAjena = $this->crearCarteraEn($proyectoAjeno);

        $this->actingAs($this->crearAdminDeMandante($propio, 'propio'));

        // Tiene proyectos.configurar EN SU PROYECTO. Si sólo se comprobara el
        // permiso, arrastraría esa autorización a la cartera del ajeno.
        Livewire::test(PasoCarteras::class, ['proyecto' => $this->modelo($proyectoPropio)])
            ->call('eliminarCartera', (int) $carteraAjena->id)
            ->assertNotFound();

        $this->assertNull(
            DB::table('carteras')->where('id', $carteraAjena->id)->value('eliminada_en'),
            'FUGA: se eliminó la cartera de otro mandante.'
        );
    }

    public function test_no_se_puede_alternar_el_estado_de_la_cartera_de_otro_mandante(): void
    {
        $propio = $this->crearMandante('MND_PROPIO');
        $ajeno = $this->crearMandante('MND_AJENO');
        $proyectoPropio = $this->crearProyectoCobranza($propio);
        $carteraAjena = $this->crearCarteraEn($this->crearProyectoCobranza($ajeno));

        $this->actingAs($this->crearAdminDeMandante($propio, 'propio'));

        Livewire::test(PasoCarteras::class, ['proyecto' => $this->modelo($proyectoPropio)])
            ->call('toggleActivo', (int) $carteraAjena->id)
            ->assertNotFound();

        $this->assertSame(
            1,
            (int) DB::table('carteras')->where('id', $carteraAjena->id)->value('activo'),
            'FUGA: se cambió el estado de la cartera de otro mandante.'
        );
    }

    // -----------------------------------------------------------------
    // Los ids que fija mount()/el servidor no se reapuntan desde el cliente
    // -----------------------------------------------------------------

    public function test_el_proyecto_no_se_puede_reapuntar_desde_el_cliente(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $otro = $this->crearProyectoCobranza($mandante);

        $this->actingAs($this->crearAdminDeMandante($mandante, 'propio'));

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(PasoCarteras::class, ['proyecto' => $this->modelo($proyecto)])
            ->set('proyecto', $this->modelo($otro));
    }

    public function test_el_id_en_edicion_no_se_puede_reapuntar_desde_el_cliente(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $ajena = $this->crearCarteraEn($this->crearProyectoCobranza($this->crearMandante('MND_AJENO')));

        $this->actingAs($this->crearAdminDeMandante($mandante, 'propio'));

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(PasoCarteras::class, ['proyecto' => $this->modelo($proyecto)])
            ->set('editandoId', (int) $ajena->id);
    }

    private function modelo(stdClass $proyecto): ProyectoModel
    {
        return ProyectoModel::query()->findOrFail($proyecto->id);
    }
}
