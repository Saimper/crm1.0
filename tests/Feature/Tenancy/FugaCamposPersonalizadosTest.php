<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Modules\CamposPersonalizados\Infrastructure\Http\Livewire\AdminCamposPersonalizados;
use App\Modules\EntidadesConfigurables\Infrastructure\Http\Livewire\AdminEntidadesConfigurables;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\EscenarioMultiMandante;
use Tests\TestCase;

/**
 * Red de fugas — superficie «campos»: /admin/campos-personalizados y
 * /admin/entidades-configurables.
 *
 * Ambas pantallas están hoy cerradas a todo el mundo salvo ADMIN_GLOBAL
 * (middleware `admin.global` en routes/web.php + `autorizar()` en cada acción
 * del Livewire). Por eso la fuga ENTRE clientes todavía no se materializa en
 * pantalla. Lo que sí existe, y es exactamente lo que impide abrirlas a
 * ADMIN_MANDANTE, es que **no tienen contexto de tenant**:
 *
 *   1. `AdminCamposPersonalizados::render()` construye `camposPorProyecto` con
 *      un SELECT sin `where` sobre `campos_personalizados`: vuelca los campos de
 *      TODOS los proyectos de TODOS los mandantes en una sola tabla, e ignora su
 *      propio `proyectoSeleccionadoId`. La vista `campos_personalizados::admin.lista`
 *      pinta una sección por proyecto con campos, así que el listado enseña
 *      código y etiqueta de las definiciones de cada cliente.
 *   2. Los dos componentes cargan `proyectos` con `DB::table('proyectos')->get()`
 *      sin filtro: el selector de proyecto es el catálogo completo de clientes.
 *   3. Todas las acciones mutadoras reciben un id crudo y lo aplican sin filtrar
 *      por proyecto ni por mandante:
 *        - `AdminCamposPersonalizados::abrirFormEditar($campoId)` → `where('id', …)`
 *          a secas, y encima reescribe `proyectoSeleccionadoId` con el proyecto
 *          ajeno; `guardar()` luego hace `update()` sobre ese id.
 *        - `desactivar($campoId)` / `activar($campoId)` → `UPDATE … WHERE id = ?`.
 *        - `AdminEntidadesConfigurables::abrirFormEditar` / `eliminarEntidad` /
 *          `abrirCamposDe` / `guardarCampo` / `abrirFormCampoEditar` /
 *          `desactivarCampo` / `activarCampo`, todas igual (y `ServicioEntidades`
 *          usa `sinScopeProyecto()` en actualizar y eliminar).
 *
 * Bloques:
 *   A) Lo que hoy está bien y hay que congelar: ADMIN_MANDANTE no entra (403).
 *   B) El listado / el selector deben respetar el proyecto elegido (ROJO hoy,
 *      salvo el listado de entidades, que ya filtra bien y aquí se fija).
 *   C) Las acciones deben rechazar un id de otro proyecto (ROJO hoy).
 *
 * NOTA SOBRE `EscenarioMultiMandante::montarDosMandantes()`
 * -------------------------------------------------------
 * No se usa aquí a propósito: `montarMandanteCompleto()` inserta en
 * `campos_personalizados` una columna `nombre` que NO existe en esa tabla, y
 * omite `etiqueta`, que es `varchar(200)` NOT NULL sin default (ver migración
 * `2026_04_18_190000_campos_create_campos_personalizados_table`). Con el helper
 * tal cual, todos los tests de este fichero reventarían con un QueryException en
 * el montaje en vez de fallar donde está la fuga. Se monta el mismo escenario
 * aquí con las primitivas de `EscenarioOperativo`, devolviendo la MISMA forma de
 * array que consume `assertNoSeFiltra()`. Cuando se corrija el Support, este
 * helper local se sustituye por `montarDosMandantes()` sin tocar los tests.
 */
final class FugaCamposPersonalizadosTest extends TestCase
{
    use EscenarioMultiMandante;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    // =====================================================================
    // A) Lo que hoy está bien: ADMIN_MANDANTE no entra. Congelarlo.
    // =====================================================================

    public function test_admin_mandante_no_entra_a_la_ruta_de_campos_personalizados(): void
    {
        $a = $this->montarMandanteConCampos('alfa');

        $this->actingAs($a['adminMandante'])
            ->get('/admin/campos-personalizados')
            ->assertStatus(403);
    }

    public function test_admin_mandante_no_monta_el_livewire_de_campos_personalizados(): void
    {
        $a = $this->montarMandanteConCampos('alfa');

        Livewire::actingAs($a['adminMandante'])
            ->test(AdminCamposPersonalizados::class)
            ->assertStatus(403);
    }

    public function test_admin_mandante_no_entra_a_la_ruta_de_entidades_configurables(): void
    {
        $a = $this->montarMandanteConCampos('alfa');

        $this->actingAs($a['adminMandante'])
            ->get('/admin/entidades-configurables')
            ->assertStatus(403);
    }

    public function test_admin_mandante_no_monta_el_livewire_de_entidades_configurables(): void
    {
        $a = $this->montarMandanteConCampos('alfa');

        Livewire::actingAs($a['adminMandante'])
            ->test(AdminEntidadesConfigurables::class)
            ->assertStatus(403);
    }

    /**
     * El supervisor del propio mandante tampoco: `autorizar()` pide
     * `campos.definir`, vetado para todo rol que no sea ADMIN_GLOBAL (F23).
     */
    public function test_supervisor_no_monta_ninguno_de_los_dos_livewire(): void
    {
        $a = $this->montarMandanteConCampos('alfa');

        Livewire::actingAs($a['supervisor'])
            ->test(AdminCamposPersonalizados::class)
            ->assertStatus(403);

        Livewire::actingAs($a['supervisor'])
            ->test(AdminEntidadesConfigurables::class)
            ->assertStatus(403);
    }

    // =====================================================================
    // B) El listado y el selector. Deben estar acotados al mandante/proyecto.
    // =====================================================================

    /**
     * ROJO HOY. `render()` hace `DB::table('campos_personalizados as c')->…->get()`
     * sin ningún `where`, y agrupa por `proyecto_id`. La tabla que ve el usuario
     * contiene los campos del mandante beta aunque esté trabajando sobre alfa.
     */
    public function test_el_listado_de_campos_solo_debe_traer_el_proyecto_seleccionado(): void
    {
        [$a, $b] = $this->montarDosMandantesConCampos();
        $admin = $this->crearAdminGlobal();

        $camposPorProyecto = Livewire::actingAs($admin)
            ->test(AdminCamposPersonalizados::class)
            ->set('proyectoSeleccionadoId', (int) $a['proyecto']->id)
            ->viewData('camposPorProyecto');

        $proyectosEnTabla = array_map('intval', $camposPorProyecto->keys()->all());

        $this->assertContains(
            (int) $a['proyecto']->id,
            $proyectosEnTabla,
            'El listado debería contener los campos del proyecto seleccionado.'
        );
        $this->assertNotContains(
            (int) $b['proyecto']->id,
            $proyectosEnTabla,
            'FUGA: el listado trae los campos del proyecto de otro mandante '
            .'(AdminCamposPersonalizados::render(), consulta a campos_personalizados sin where).'
        );
    }

    /**
     * ROJO HOY. Misma fuga vista desde el HTML: el código y la etiqueta del campo
     * del mandante beta se pintan en la tabla que mira el operador de alfa.
     */
    public function test_el_html_del_listado_no_debe_mostrar_campos_del_mandante_ajeno(): void
    {
        [$a, $b] = $this->montarDosMandantesConCampos();
        $admin = $this->crearAdminGlobal();

        $html = Livewire::actingAs($admin)
            ->test(AdminCamposPersonalizados::class)
            ->set('proyectoSeleccionadoId', (int) $a['proyecto']->id)
            ->html();

        $this->assertStringContainsString(
            $a['campoCodigo'],
            $html,
            'El campo del proyecto seleccionado sí debe verse.'
        );
        $this->assertStringNotContainsString(
            $b['campoCodigo'],
            $html,
            'FUGA: se pinta el código del campo personalizado del otro mandante.'
        );
        $this->assertStringNotContainsString(
            $b['campoEtiqueta'],
            $html,
            'FUGA: se pinta la etiqueta del campo personalizado del otro mandante.'
        );
    }

    /**
     * ROJO HOY. El rastro más ancho de todos: `render()` carga `proyectos` con
     * `DB::table('proyectos')->orderBy('codigo')->get()` y la vista lo escupe
     * entero en el `<select>` del drawer. Estando en alfa se lee el catálogo de
     * proyectos de todos los clientes de la instalación.
     */
    public function test_la_pantalla_de_campos_no_debe_dejar_rastro_del_mandante_ajeno(): void
    {
        [$a, $b] = $this->montarDosMandantesConCampos();
        $admin = $this->crearAdminGlobal();

        $html = Livewire::actingAs($admin)
            ->test(AdminCamposPersonalizados::class)
            ->set('proyectoSeleccionadoId', (int) $a['proyecto']->id)
            ->call('abrirFormCrear')
            ->html();

        $this->assertNoSeFiltra($html, $b, '/admin/campos-personalizados trabajando sobre alfa');
    }

    /**
     * ROJO HOY. Mismo `SELECT * FROM proyectos` en el otro componente.
     */
    public function test_el_selector_de_proyectos_de_entidades_no_debe_listar_proyectos_ajenos(): void
    {
        [$a, $b] = $this->montarDosMandantesConCampos();
        $admin = $this->crearAdminGlobal();

        $proyectos = Livewire::actingAs($admin)
            ->test(AdminEntidadesConfigurables::class)
            ->set('proyectoSeleccionadoId', (int) $a['proyecto']->id)
            ->viewData('proyectos');

        $ids = $this->idsDe($proyectos);

        $this->assertContains((int) $a['proyecto']->id, $ids);
        $this->assertNotContains(
            (int) $b['proyecto']->id,
            $ids,
            'FUGA: el selector de proyecto de /admin/entidades-configurables lista '
            .'los proyectos de todos los mandantes (AdminEntidadesConfigurables::render()).'
        );
    }

    /**
     * VERDE HOY (fija comportamiento correcto). `AdminEntidadesConfigurables::render()`
     * sí filtra las entidades por `proyectoSeleccionadoId`. Este test existe para
     * que la corrección de la fuga de campos no lo rompa por el camino.
     */
    public function test_el_listado_de_entidades_ya_respeta_el_proyecto_seleccionado(): void
    {
        [$a, $b] = $this->montarDosMandantesConCampos();
        $admin = $this->crearAdminGlobal();

        $entidades = Livewire::actingAs($admin)
            ->test(AdminEntidadesConfigurables::class)
            ->set('proyectoSeleccionadoId', (int) $a['proyecto']->id)
            ->viewData('entidades');

        $ids = $this->idsDe($entidades);

        $this->assertContains((int) $a['entidadId'], $ids);
        $this->assertNotContains(
            (int) $b['entidadId'],
            $ids,
            'El listado de entidades no debe traer la entidad de otro mandante.'
        );
    }

    // =====================================================================
    // C) Las acciones mutadoras con id crudo. Deben rechazar ids ajenos.
    // =====================================================================

    /**
     * ROJO HOY. `abrirFormEditar()` carga el campo por id y, peor, arrastra la
     * pantalla al proyecto ajeno (`$this->proyectoSeleccionadoId = $row->proyecto_id`).
     */
    public function test_abrir_form_editar_no_debe_cargar_un_campo_de_otro_proyecto(): void
    {
        [$a, $b] = $this->montarDosMandantesConCampos();
        $admin = $this->crearAdminGlobal();

        $vista = Livewire::actingAs($admin)
            ->test(AdminCamposPersonalizados::class)
            ->set('proyectoSeleccionadoId', (int) $a['proyecto']->id)
            ->call('abrirFormEditar', (int) $b['campoId']);

        $vista->assertSet('campoEditandoId', null);
        $vista->assertSet('formVisible', false);
        $vista->assertNotSet('form.codigo', $b['campoCodigo']);
        $vista->assertSet('proyectoSeleccionadoId', (int) $a['proyecto']->id);
    }

    /**
     * ROJO HOY, y es la consecuencia grave de la anterior: `guardar()` hace
     * `update()` sobre `campoEditandoId` sin comprobar a qué proyecto pertenece
     * esa fila. El payload lleva `proyecto_id` del form, así que la definición
     * del mandante beta acaba reasignada al proyecto de alfa.
     */
    public function test_guardar_no_debe_reescribir_un_campo_de_otro_proyecto(): void
    {
        [$a, $b] = $this->montarDosMandantesConCampos();
        $admin = $this->crearAdminGlobal();

        Livewire::actingAs($admin)
            ->test(AdminCamposPersonalizados::class)
            ->set('proyectoSeleccionadoId', (int) $a['proyecto']->id)
            ->set('campoEditandoId', (int) $b['campoId'])
            ->set('form.proyecto_id', (int) $a['proyecto']->id)
            ->set('form.ambito', 'caso')
            ->set('form.ambito_id', (int) $a['cartera']->id)
            ->set('form.codigo', 'secuestrado')
            ->set('form.etiqueta', 'Secuestrado por alfa')
            ->set('form.tipo', 'texto_corto')
            ->call('guardar');

        $fila = DB::table('campos_personalizados')->where('id', $b['campoId'])->first();

        $this->assertNotNull($fila);
        $this->assertSame(
            (int) $b['proyecto']->id,
            (int) $fila->proyecto_id,
            'FUGA: la definición de campo del mandante beta quedó reasignada al proyecto de alfa.'
        );
        $this->assertSame(
            $b['campoCodigo'],
            (string) $fila->codigo,
            'FUGA: se reescribió el código de un campo personalizado de otro mandante.'
        );
    }

    /**
     * ROJO HOY. `desactivar()` es un `UPDATE campos_personalizados SET activo = 0
     * WHERE id = ?`. Nada impide apagar un campo de otro cliente.
     */
    public function test_desactivar_no_debe_apagar_un_campo_de_otro_proyecto(): void
    {
        [$a, $b] = $this->montarDosMandantesConCampos();
        $admin = $this->crearAdminGlobal();

        Livewire::actingAs($admin)
            ->test(AdminCamposPersonalizados::class)
            ->set('proyectoSeleccionadoId', (int) $a['proyecto']->id)
            ->call('desactivar', (int) $b['campoId']);

        $this->assertTrue(
            $this->campoEstaActivo((int) $b['campoId']),
            'FUGA: se desactivó un campo personalizado de otro mandante desde el listado.'
        );
    }

    /**
     * ROJO HOY. Simétrico al anterior: `activar()` reenciende cualquier id.
     */
    public function test_activar_no_debe_encender_un_campo_de_otro_proyecto(): void
    {
        [$a, $b] = $this->montarDosMandantesConCampos();
        $admin = $this->crearAdminGlobal();

        DB::table('campos_personalizados')->where('id', $b['campoId'])->update(['activo' => false]);

        Livewire::actingAs($admin)
            ->test(AdminCamposPersonalizados::class)
            ->set('proyectoSeleccionadoId', (int) $a['proyecto']->id)
            ->call('activar', (int) $b['campoId']);

        $this->assertFalse(
            $this->campoEstaActivo((int) $b['campoId']),
            'FUGA: se reactivó un campo personalizado de otro mandante desde el listado.'
        );
    }

    /**
     * ROJO HOY. Misma forma en entidades: el form se abre con la entidad ajena y
     * la pantalla salta al proyecto del otro mandante.
     */
    public function test_abrir_form_editar_no_debe_cargar_una_entidad_de_otro_proyecto(): void
    {
        [$a, $b] = $this->montarDosMandantesConCampos();
        $admin = $this->crearAdminGlobal();

        $vista = Livewire::actingAs($admin)
            ->test(AdminEntidadesConfigurables::class)
            ->set('proyectoSeleccionadoId', (int) $a['proyecto']->id)
            ->call('abrirFormEditar', (int) $b['entidadId']);

        $vista->assertSet('entidadEditandoId', null);
        $vista->assertSet('formVisible', false);
        $vista->assertNotSet('formCodigo', $b['entidadCodigo']);
        $vista->assertSet('proyectoSeleccionadoId', (int) $a['proyecto']->id);
    }

    /**
     * ROJO HOY. `eliminarEntidad()` delega en `ServicioEntidades::eliminarEntidad()`,
     * que usa `sinScopeProyecto()->where('id', $entidadId)`: borra lógicamente la
     * entidad de cualquier proyecto.
     */
    public function test_eliminar_entidad_no_debe_borrar_una_entidad_de_otro_proyecto(): void
    {
        [$a, $b] = $this->montarDosMandantesConCampos();
        $admin = $this->crearAdminGlobal();

        Livewire::actingAs($admin)
            ->test(AdminEntidadesConfigurables::class)
            ->set('proyectoSeleccionadoId', (int) $a['proyecto']->id)
            ->call('eliminarEntidad', (int) $b['entidadId']);

        $fila = DB::table('entidades_configurables')->where('id', $b['entidadId'])->first();

        $this->assertNotNull($fila);
        $this->assertNull(
            $fila->eliminada_en,
            'FUGA: se eliminó (soft) la entidad configurable de otro mandante.'
        );
        $this->assertTrue(
            (bool) $fila->activo,
            'FUGA: se desactivó la entidad configurable de otro mandante.'
        );
    }

    /**
     * ROJO HOY. `abrirCamposDe()` guarda el id sin validar y `render()` lista
     * `campos_personalizados` por `ambito_id` sin `proyecto_id`: el panel muestra
     * la definición de campos del otro cliente.
     */
    public function test_abrir_campos_de_una_entidad_ajena_no_debe_exponer_sus_campos(): void
    {
        [$a, $b] = $this->montarDosMandantesConCampos();
        $admin = $this->crearAdminGlobal();

        $campos = Livewire::actingAs($admin)
            ->test(AdminEntidadesConfigurables::class)
            ->set('proyectoSeleccionadoId', (int) $a['proyecto']->id)
            ->call('abrirCamposDe', (int) $b['entidadId'])
            ->viewData('campos');

        $this->assertNotContains(
            (int) $b['campoEntidadId'],
            $this->idsDe($campos),
            'FUGA: el panel de campos muestra la definición de la entidad configurable de otro mandante.'
        );
    }

    /**
     * ROJO HOY. `guardarCampo()` escribe usando el `proyecto_id` de la entidad
     * abierta, sin comprobar que esa entidad sea del proyecto en pantalla: se
     * puede inyectar una columna nueva en la entidad configurable de otro cliente.
     */
    public function test_guardar_campo_no_debe_inyectar_una_columna_en_una_entidad_ajena(): void
    {
        [$a, $b] = $this->montarDosMandantesConCampos();
        $admin = $this->crearAdminGlobal();

        Livewire::actingAs($admin)
            ->test(AdminEntidadesConfigurables::class)
            ->set('proyectoSeleccionadoId', (int) $a['proyecto']->id)
            ->call('abrirCamposDe', (int) $b['entidadId'])
            ->call('abrirFormCampoCrear')
            ->set('formCampoCodigo', 'inyectado')
            ->set('formCampoEtiqueta', 'Inyectado desde alfa')
            ->set('formCampoTipo', 'texto_corto')
            ->call('guardarCampo');

        $this->assertDatabaseMissing('campos_personalizados', [
            'proyecto_id' => $b['proyecto']->id,
            'ambito' => 'entidad_configurable',
            'ambito_id' => $b['entidadId'],
            'codigo' => 'inyectado',
        ]);
    }

    /**
     * ROJO HOY. `abrirFormCampoEditar()` carga cualquier fila de
     * `campos_personalizados` por id, sin mirar proyecto ni entidad.
     */
    public function test_abrir_form_campo_editar_no_debe_cargar_un_campo_de_otro_proyecto(): void
    {
        [$a, $b] = $this->montarDosMandantesConCampos();
        $admin = $this->crearAdminGlobal();

        $vista = Livewire::actingAs($admin)
            ->test(AdminEntidadesConfigurables::class)
            ->set('proyectoSeleccionadoId', (int) $a['proyecto']->id)
            ->call('abrirCamposDe', (int) $a['entidadId'])
            ->call('abrirFormCampoEditar', (int) $b['campoEntidadId']);

        $vista->assertSet('campoEditandoId', null);
        $vista->assertSet('formCampoVisible', false);
        $vista->assertNotSet('formCampoCodigo', $b['campoEntidadCodigo']);
    }

    /**
     * ROJO HOY. `desactivarCampo()` es un `UPDATE … WHERE id = ?` desnudo.
     */
    public function test_desactivar_campo_no_debe_apagar_un_campo_de_entidad_ajena(): void
    {
        [$a, $b] = $this->montarDosMandantesConCampos();
        $admin = $this->crearAdminGlobal();

        Livewire::actingAs($admin)
            ->test(AdminEntidadesConfigurables::class)
            ->set('proyectoSeleccionadoId', (int) $a['proyecto']->id)
            ->call('abrirCamposDe', (int) $a['entidadId'])
            ->call('desactivarCampo', (int) $b['campoEntidadId']);

        $this->assertTrue(
            $this->campoEstaActivo((int) $b['campoEntidadId']),
            'FUGA: se desactivó el campo de una entidad configurable de otro mandante.'
        );
    }

    /**
     * ROJO HOY. Simétrico: `activarCampo()` reenciende cualquier id.
     */
    public function test_activar_campo_no_debe_encender_un_campo_de_entidad_ajena(): void
    {
        [$a, $b] = $this->montarDosMandantesConCampos();
        $admin = $this->crearAdminGlobal();

        DB::table('campos_personalizados')->where('id', $b['campoEntidadId'])->update(['activo' => false]);

        Livewire::actingAs($admin)
            ->test(AdminEntidadesConfigurables::class)
            ->set('proyectoSeleccionadoId', (int) $a['proyecto']->id)
            ->call('abrirCamposDe', (int) $a['entidadId'])
            ->call('activarCampo', (int) $b['campoEntidadId']);

        $this->assertFalse(
            $this->campoEstaActivo((int) $b['campoEntidadId']),
            'FUGA: se reactivó el campo de una entidad configurable de otro mandante.'
        );
    }

    // =====================================================================
    // Escenario
    // =====================================================================

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function montarDosMandantesConCampos(): array
    {
        return [
            $this->montarMandanteConCampos('alfa'),
            $this->montarMandanteConCampos('beta'),
        ];
    }

    /**
     * Stand-in local de `EscenarioMultiMandante::montarMandanteCompleto()` — ver
     * la nota del docblock de la clase. Devuelve las mismas claves que consume
     * `assertNoSeFiltra()` (mandante, proyecto, cartera, gestor, supervisor,
     * adminMandante) más lo propio de esta superficie.
     *
     * @return array<string, mixed>
     */
    private function montarMandanteConCampos(string $etiqueta): array
    {
        $sufijo = strtoupper($etiqueta).'_'.strtoupper(Str::random(6));

        $mandante = $this->crearMandante('MND_'.$sufijo);
        $proyecto = $this->crearProyectoCobranza($mandante);
        $cartera = $this->crearCarteraEn($proyecto);

        $campoCodigo = 'campo_'.strtolower($sufijo);
        $campoEtiqueta = 'Campo exclusivo de '.$sufijo;
        $campoId = (int) DB::table('campos_personalizados')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'ambito' => 'caso',
            'ambito_id' => $cartera->id,
            'codigo' => $campoCodigo,
            'etiqueta' => $campoEtiqueta,
            'tipo' => 'texto_corto',
            'obligatorio' => false,
            'activo' => true,
            'orden' => 100,
        ]);

        $entidadCodigo = 'ENT_'.$sufijo;
        $entidadId = (int) DB::table('entidades_configurables')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'cartera_id' => null,
            'codigo' => $entidadCodigo,
            'nombre' => 'Entidad exclusiva de '.$sufijo,
            'relacion_con' => 'ninguna',
            'activo' => true,
        ]);

        $campoEntidadCodigo = 'campo_ent_'.strtolower($sufijo);
        $campoEntidadId = (int) DB::table('campos_personalizados')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'ambito' => 'entidad_configurable',
            'ambito_id' => $entidadId,
            'codigo' => $campoEntidadCodigo,
            'etiqueta' => 'Campo de entidad de '.$sufijo,
            'tipo' => 'texto_corto',
            'obligatorio' => false,
            'activo' => true,
            'orden' => 100,
        ]);

        return [
            'mandante' => $mandante,
            'proyecto' => $proyecto,
            'cartera' => $cartera,
            'campoId' => $campoId,
            'campoCodigo' => $campoCodigo,
            'campoEtiqueta' => $campoEtiqueta,
            'entidadId' => $entidadId,
            'entidadCodigo' => $entidadCodigo,
            'campoEntidadId' => $campoEntidadId,
            'campoEntidadCodigo' => $campoEntidadCodigo,
            'adminMandante' => $this->crearAdminDeMandante($mandante, $etiqueta),
            'supervisor' => $this->crearSupervisor($proyecto),
            'gestor' => $this->crearGestor($proyecto),
        ];
    }

    private function campoEstaActivo(int $campoId): bool
    {
        return (bool) DB::table('campos_personalizados')->where('id', $campoId)->value('activo');
    }
}
