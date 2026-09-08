<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\CamposPersonalizados;

use App\Modules\CamposPersonalizados\Infrastructure\Http\Livewire\AdminCamposPersonalizados;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * CRUD administrativo de campos personalizados (`/admin/campos-personalizados`).
 *
 * El escenario se monta aquí con `EscenarioOperativo` en vez de leerlo de los
 * *DemoSeeder borrados. Un detalle no es cosmético: la pantalla trabaja acotada
 * al MANDANTE (`proyectosEnAlcance()`), así que los dos proyectos del test del
 * ámbito cruzado tienen que colgar del MISMO mandante — como colgaban los cuatro
 * proyectos demo de `BPO_DEMO`. Si se montan en mandantes distintos, el segundo
 * proyecto queda fuera de alcance y la pantalla falla por 403 antes de llegar a
 * la validación de `ambito_id`, que es lo que el test quiere ver.
 */
final class AdminCamposPersonalizadosTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_crea_campo_personalizado(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto, 'CONSUMO');
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(AdminCamposPersonalizados::class)
            ->set('proyectoSeleccionadoId', (int) $proyecto->id)
            ->call('abrirFormCrear')
            ->assertSet('formVisible', true)
            ->set('form.proyecto_id', (int) $proyecto->id)
            ->set('form.ambito', 'caso')
            ->set('form.ambito_id', (int) $cartera->id)
            ->set('form.codigo', 'observacion_gerente')
            ->set('form.etiqueta', 'Observación del gerente')
            ->set('form.tipo', 'texto_corto')
            ->set('form.longitud_max', 200)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('formVisible', false);

        $this->assertDatabaseHas('campos_personalizados', [
            'proyecto_id' => $proyecto->id,
            'ambito' => 'caso',
            'ambito_id' => $cartera->id,
            'codigo' => 'observacion_gerente',
            'etiqueta' => 'Observación del gerente',
            'tipo' => 'texto_corto',
            'activo' => true,
        ]);
    }

    /**
     * La intención original era «dos campos del mismo ámbito no pueden compartir
     * código», y se comprobaba esperando un error de validación en `form.codigo`.
     *
     * La pantalla ya no rechaza: desde la política B6 de códigos
     * (`GeneradorCodigo::resolverConflicto`, invocado en `guardar()`) el conflicto
     * se resuelve sufijando `_2`, `_3`, … El invariante que el test protege sigue
     * siendo el mismo y se comprueba igual de duro — no hay dos filas con el
     * mismo `codigo` en `(proyecto, ámbito, ámbito_id)` —, pero se afirma sobre el
     * resultado en base de datos y no sobre el mensaje de error, que es lo que
     * cambió de forma deliberada en la aplicación.
     */
    public function test_admin_rechaza_codigo_duplicado_en_mismo_ambito(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto, 'CONSUMO');
        $this->actingAs($this->crearAdminGlobal());

        DB::table('campos_personalizados')->insert([
            'proyecto_id' => $proyecto->id,
            'ambito' => 'caso',
            'ambito_id' => $cartera->id,
            'codigo' => 'existente',
            'etiqueta' => 'Ya existe',
            'tipo' => 'texto_corto',
            'obligatorio' => false,
            'activo' => true,
            'orden' => 100,
        ]);

        Livewire::test(AdminCamposPersonalizados::class)
            ->set('proyectoSeleccionadoId', (int) $proyecto->id)
            ->call('abrirFormCrear')
            ->set('form.proyecto_id', (int) $proyecto->id)
            ->set('form.ambito', 'caso')
            ->set('form.ambito_id', (int) $cartera->id)
            ->set('form.codigo', 'existente')
            ->set('form.etiqueta', 'Duplicado')
            ->set('form.tipo', 'texto_corto')
            ->call('guardar');

        $codigos = DB::table('campos_personalizados')
            ->where('proyecto_id', $proyecto->id)
            ->where('ambito', 'caso')
            ->where('ambito_id', $cartera->id)
            ->orderBy('id')
            ->pluck('codigo')
            ->all();

        // El test no debe pasar «porque no se guardó nada»: el segundo campo se
        // crea, y lo que se comprueba es que no se llevó por delante el código
        // del primero.
        $this->assertCount(2, $codigos);

        $this->assertSame(
            $codigos,
            array_values(array_unique($codigos)),
            'Dos campos del mismo ámbito acabaron compartiendo código.'
        );
        $this->assertSame(
            1,
            DB::table('campos_personalizados')
                ->where('proyecto_id', $proyecto->id)
                ->where('ambito', 'caso')
                ->where('ambito_id', $cartera->id)
                ->where('codigo', 'existente')
                ->count(),
            'El código «existente» quedó duplicado dentro del mismo ámbito.'
        );
    }

    public function test_admin_rechaza_ambito_id_que_no_pertenece_al_proyecto(): void
    {
        // Mismo mandante para los dos proyectos: así ambos están en alcance y el
        // rechazo tiene que venir de la validación del ámbito, no del tenant.
        $mandante = $this->crearMandante();
        $proyectoCob = $this->crearProyectoCobranza($mandante);
        $proyectoCx = $this->crearProyectoCx($mandante);
        $carteraCx = $this->crearCarteraEn($proyectoCx);
        $this->crearCarteraEn($proyectoCob);

        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(AdminCamposPersonalizados::class)
            ->set('proyectoSeleccionadoId', (int) $proyectoCob->id)
            ->call('abrirFormCrear')
            ->set('form.proyecto_id', (int) $proyectoCob->id)
            ->set('form.ambito', 'caso')
            ->set('form.ambito_id', (int) $carteraCx->id) // cartera de otro proyecto
            ->set('form.codigo', 'cruzado')
            ->set('form.etiqueta', 'Cruzado')
            ->set('form.tipo', 'texto_corto')
            ->call('guardar')
            ->assertHasErrors(['form.ambito_id']);

        $this->assertSame(0, DB::table('campos_personalizados')->where('codigo', 'cruzado')->count());
    }

    public function test_admin_desactiva_y_reactiva_campo(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $this->actingAs($this->crearAdminGlobal());

        $campoId = (int) DB::table('campos_personalizados')->insertGetId([
            'proyecto_id' => $proyecto->id, 'ambito' => 'caso', 'ambito_id' => $cartera->id,
            'codigo' => 'campo_demo', 'etiqueta' => 'Demo', 'tipo' => 'texto_corto',
            'obligatorio' => false, 'activo' => true, 'orden' => 100,
        ]);

        Livewire::test(AdminCamposPersonalizados::class)
            ->set('proyectoSeleccionadoId', (int) $proyecto->id)
            ->call('desactivar', $campoId);
        $this->assertFalse((bool) DB::table('campos_personalizados')->where('id', $campoId)->value('activo'));

        Livewire::test(AdminCamposPersonalizados::class)
            ->set('proyectoSeleccionadoId', (int) $proyecto->id)
            ->call('activar', $campoId);
        $this->assertTrue((bool) DB::table('campos_personalizados')->where('id', $campoId)->value('activo'));
    }

    public function test_ruta_admin_rechaza_a_no_admin_global(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        $this->actingAs($gestor)
            ->get(route('admin.campos-personalizados'))
            ->assertStatus(403);
    }

    public function test_ruta_admin_responde_200_a_admin_global(): void
    {
        $this->crearProyectoCobranza();

        $this->actingAs($this->crearAdminGlobal())
            ->get(route('admin.campos-personalizados'))
            ->assertStatus(200);
    }
}
