<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tenancy;

use App\Modules\Tenancy\Infrastructure\Http\Livewire\AdminProyectos;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\SelectorProyecto;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Archivar un proyecto (D2).
 *
 * Un proyecto se llega a alcanzar por tres caminos —la pantalla de
 * administración, el selector y la URL /proyectos/{id}—, así que archivar sólo
 * vale si los cierra los tres. Esconderlo de una lista y dejar la URL abierta
 * no es archivar: es maquillar.
 *
 * Lo que archivar NO es: borrar. La fila se queda con `eliminada_en` puesto
 * (§4) porque de `proyecto_id` cuelga la operación entera y las gestiones no se
 * borran nunca (§13.11).
 */
final class ArchivarProyectoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_archivar_marca_el_borrado_logico_sin_borrar_la_fila(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(AdminProyectos::class)
            ->call('archivar', (int) $proyecto->id)
            ->assertOk();

        $fila = DB::table('proyectos')->where('id', $proyecto->id)->first();

        $this->assertNotNull($fila, 'La fila del proyecto no puede desaparecer: §4 manda borrado lógico.');
        $this->assertNotNull($fila->eliminada_en);
        $this->assertFalse(
            (bool) $fila->activo,
            'Un proyecto archivado se apaga: si algún día vuelve, que vuelva parado y no operando.'
        );
    }

    /**
     * El camino que de verdad importa. El supervisor entraba ayer por esta URL
     * y su asignación en `usuario_proyecto_rol` sigue intacta: quien tiene que
     * cerrarle la puerta es el middleware que resuelve el proyecto activo.
     */
    public function test_un_proyecto_archivado_deja_de_ser_alcanzable_por_su_url(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);

        $this->actingAs($supervisor)->get("/proyectos/{$proyecto->id}")->assertOk();

        $this->actingAs($this->crearAdminGlobal());
        Livewire::test(AdminProyectos::class)->call('archivar', (int) $proyecto->id);

        $this->actingAs($supervisor)->get("/proyectos/{$proyecto->id}")->assertNotFound();
    }

    /**
     * El configurador se llega por `public_id` y no por el listado, así que
     * quien tenga el enlace guardado sigue teniéndolo. Ahí la puerta la cierra
     * el binding de ruta, que no resuelve filas con borrado lógico.
     */
    public function test_un_proyecto_archivado_no_se_puede_seguir_configurando(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $admin = $this->crearAdminGlobal();
        $url = "/admin/proyectos/{$proyecto->public_id}/configurar";

        $this->actingAs($admin)->get($url)->assertOk();

        Livewire::actingAs($admin)->test(AdminProyectos::class)->call('archivar', (int) $proyecto->id);

        $this->actingAs($admin)->get($url)->assertNotFound();
    }

    public function test_un_proyecto_archivado_no_vuelve_por_el_buscador_de_administracion(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->actingAs($this->crearAdminGlobal());

        $componente = Livewire::test(AdminProyectos::class)
            ->call('archivar', (int) $proyecto->id)
            ->set('busqueda', (string) $proyecto->codigo);

        $this->assertSame(
            [],
            $this->idsDeProyectos($componente->viewData('proyectos')),
            'Buscarlo por su código es la puerta de atrás de cualquier listado filtrado.'
        );
    }

    /**
     * El gestor que se queda sin su único proyecto no puede acabar redirigido a
     * él: el selector auto-redirige cuando sólo hay uno accesible, y el que
     * quedaba está archivado.
     */
    public function test_el_selector_no_redirige_al_unico_proyecto_si_esta_archivado(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        $this->actingAs($this->crearAdminGlobal());
        Livewire::test(AdminProyectos::class)->call('archivar', (int) $proyecto->id);

        $componente = Livewire::actingAs($gestor)->test(SelectorProyecto::class);

        $this->assertSame([], $this->idsDeProyectos($componente->viewData('proyectos')));
    }

    public function test_archivar_no_arrastra_a_los_demas_proyectos_del_mandante(): void
    {
        $mandante = $this->crearMandante('MND_ARCHIVO');
        $archivado = $this->crearProyectoCobranza($mandante);
        $superviviente = $this->crearProyectoCx($mandante);
        $this->actingAs($this->crearAdminGlobal());

        $componente = Livewire::test(AdminProyectos::class)->call('archivar', (int) $archivado->id);

        $this->assertSame(
            [(int) $superviviente->id],
            $this->idsDeProyectos($componente->viewData('proyectos')),
        );
        $this->assertNull(DB::table('proyectos')->where('id', $superviviente->id)->value('eliminada_en'));
    }

    /**
     * El id llega del cliente, así que puede señalar a cualquier cosa. Apuntar a
     * una fila que no existe se ignora en silencio, igual que en desactivar y
     * activar: no hay nada que proteger ni nada que contar.
     */
    public function test_archivar_un_proyecto_inexistente_no_rompe_la_pantalla(): void
    {
        $this->crearProyectoCobranza();
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(AdminProyectos::class)
            ->call('archivar', 999999)
            ->assertOk();
    }

    /**
     * Dos botones grises, uno al lado del otro, uno reversible y el otro no.
     * Si la pantalla no dice cuál es cuál, alguien archivará queriendo pausar.
     */
    public function test_la_pantalla_explica_la_diferencia_entre_desactivar_y_archivar(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->actingAs($this->crearAdminGlobal());

        $html = Livewire::test(AdminProyectos::class)
            ->call('abrirFormEditar', (int) $proyecto->id)
            ->html();

        $textos = [
            __('tenancy.btn_deactivate'),
            __('tenancy.btn_archive'),
            __('tenancy.deactivate_hint_proyecto'),
            __('tenancy.archive_hint_proyecto'),
        ];

        foreach ($textos as $texto) {
            $this->assertStringContainsString((string) e($texto), $html);
        }
    }

    /**
     * @param  iterable<mixed>  $filas
     * @return list<int>
     */
    private function idsDeProyectos(iterable $filas): array
    {
        $ids = [];
        foreach ($filas as $fila) {
            $ids[] = (int) (is_object($fila) ? $fila->id : $fila['id']);
        }

        return $ids;
    }
}
