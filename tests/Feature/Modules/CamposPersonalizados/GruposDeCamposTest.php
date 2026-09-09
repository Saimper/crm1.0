<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\CamposPersonalizados;

use App\Modules\CamposPersonalizados\Application\Services\ServicioCamposPersonalizados;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\AmbitoCampo;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\PasoCamposPersonalizados;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Los grupos ordenan los campos en pantalla, y son un catálogo por proyecto.
 *
 * No son texto libre a propósito: agrupar es lo único que hacen, y §13.2
 * prohíbe el texto libre justo para lo que el negocio agrupa. Con 255 campos y
 * varios administradores, un `varchar` daría «Saldos», «saldos» y «Saldo » como
 * tres grupos el primer mes.
 */
final class GruposDeCamposTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /**
     * El importador escribe `orden = 0` para todos, así que 98 campos empataban
     * y el orden que veía el gestor no lo garantizaba nadie.
     */
    public function test_los_campos_salen_por_grupo_luego_orden_luego_id(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);

        $deuda = $this->grupo($proyecto, 'Deuda', 10);
        $contacto = $this->grupo($proyecto, 'Contacto', 20);

        $sinGrupo = $this->campo($proyecto, $cartera, 'zzz_sin_grupo', null, 0);
        $tel = $this->campo($proyecto, $cartera, 'telefono', $contacto, 0);
        $saldo2 = $this->campo($proyecto, $cartera, 'saldo_b', $deuda, 20);
        $saldo1 = $this->campo($proyecto, $cartera, 'saldo_a', $deuda, 10);

        $codigos = $this->app->make(ServicioCamposPersonalizados::class)
            ->campos((int) $proyecto->id, AmbitoCampo::CASO, (int) $cartera->id)
            ->pluck('codigo')->all();

        $this->assertSame(['saldo_a', 'saldo_b', 'telefono', 'zzz_sin_grupo'], $codigos);
    }

    public function test_los_campos_sin_grupo_van_al_final_aunque_su_orden_sea_bajo(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $grupo = $this->grupo($proyecto, 'Deuda', 10);

        $this->campo($proyecto, $cartera, 'suelto', null, 0);
        $this->campo($proyecto, $cartera, 'agrupado', $grupo, 999);

        $codigos = $this->app->make(ServicioCamposPersonalizados::class)
            ->campos((int) $proyecto->id, AmbitoCampo::CASO, (int) $cartera->id)
            ->pluck('codigo')->all();

        $this->assertSame(['agrupado', 'suelto'], $codigos);
    }

    public function test_el_orden_es_estable_cuando_todos_empatan_en_cero(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);

        foreach (['c', 'a', 'b'] as $codigo) {
            $this->campo($proyecto, $cartera, $codigo, null, 0);
        }

        $servicio = $this->app->make(ServicioCamposPersonalizados::class);
        $primera = $servicio->campos((int) $proyecto->id, AmbitoCampo::CASO, (int) $cartera->id)->pluck('codigo')->all();
        $segunda = $servicio->campos((int) $proyecto->id, AmbitoCampo::CASO, (int) $cartera->id)->pluck('codigo')->all();

        $this->assertSame(['c', 'a', 'b'], $primera, 'con el orden empatado manda el id, que es el de creación');
        $this->assertSame($primera, $segunda);
    }

    public function test_el_wizard_crea_un_grupo_derivando_su_codigo_del_nombre(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $admin = $this->crearAdminGlobal();

        Livewire::actingAs($admin)
            ->test(PasoCamposPersonalizados::class, ['proyecto' => ProyectoModel::find($proyecto->id)])
            ->set('grupoNuevo', 'Deuda y saldos')
            ->call('crearGrupo')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('grupos_campo', [
            'proyecto_id' => $proyecto->id,
            'codigo' => 'DEUDA_Y_SALDOS',
            'nombre' => 'Deuda y saldos',
        ]);
    }

    public function test_no_se_puede_repetir_el_nombre_de_un_grupo_en_el_mismo_proyecto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->grupo($proyecto, 'Deuda', 10);
        $admin = $this->crearAdminGlobal();

        Livewire::actingAs($admin)
            ->test(PasoCamposPersonalizados::class, ['proyecto' => ProyectoModel::find($proyecto->id)])
            ->set('grupoNuevo', 'Deuda')
            ->call('crearGrupo')
            ->assertHasErrors('grupoNuevo');

        $this->assertSame(1, DB::table('grupos_campo')->where('proyecto_id', $proyecto->id)->count());
    }

    /** Borrar un grupo con campos dentro los dejaría sueltos sin avisar. */
    public function test_no_se_borra_un_grupo_que_tiene_campos(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $grupo = $this->grupo($proyecto, 'Deuda', 10);
        $this->campo($proyecto, $cartera, 'saldo', $grupo, 0);
        $admin = $this->crearAdminGlobal();

        Livewire::actingAs($admin)
            ->test(PasoCamposPersonalizados::class, ['proyecto' => ProyectoModel::find($proyecto->id)])
            ->call('eliminarGrupo', $grupo);

        $this->assertDatabaseHas('grupos_campo', ['id' => $grupo]);
    }

    /** Multi-tenancy (§12): un grupo de un proyecto no se ve ni se usa en otro. */
    public function test_los_grupos_no_cruzan_proyectos(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCobranza();
        $grupoDeA = $this->grupo($proyectoA, 'Deuda', 10);
        $admin = $this->crearAdminGlobal();

        $componente = Livewire::actingAs($admin)
            ->test(PasoCamposPersonalizados::class, ['proyecto' => ProyectoModel::find($proyectoB->id)]);

        $ids = collect($componente->viewData('grupos'))->pluck('id')->all();
        $this->assertNotContains($grupoDeA, $ids);

        // Y tampoco se puede asignar por payload: la validación exige que el
        // grupo sea del proyecto del formulario.
        $cartera = $this->crearCarteraEn($proyectoB);
        $componente->call('abrirFormCrear')
            ->set('form.ambito', 'caso')
            ->set('form.ambito_id', $cartera->id)
            ->set('form.codigo', 'x')
            ->set('form.etiqueta', 'X')
            ->set('form.grupo_campo_id', $grupoDeA)
            ->call('guardar')
            ->assertHasErrors('form.grupo_campo_id');
    }

    private function grupo(stdClass $proyecto, string $nombre, int $orden): int
    {
        return (int) DB::table('grupos_campo')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => strtoupper(str_replace(' ', '_', $nombre)),
            'nombre' => $nombre,
            'activo' => true,
            'orden' => $orden,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
    }

    private function campo(stdClass $proyecto, stdClass $cartera, string $codigo, ?int $grupoId, int $orden): int
    {
        return (int) DB::table('campos_personalizados')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'ambito' => 'caso',
            'ambito_id' => $cartera->id,
            'grupo_campo_id' => $grupoId,
            'codigo' => $codigo,
            'etiqueta' => ucfirst($codigo),
            'tipo' => 'texto_corto',
            'obligatorio' => false,
            'activo' => true,
            'visible_en_gestion' => true,
            'orden' => $orden,
            'reglas' => json_encode([]),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
    }
}
