<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\AdminProyectos;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\SelectorProyecto;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioMultiMandante;
use Tests\TestCase;

/**
 * Red de seguridad de /admin/proyectos (Fase 0).
 *
 * La pantalla filtra el LISTADO por mandante (F39) pero deja abiertas dos
 * puertas grandes:
 *
 *   1. El `<select>` de mandante sigue editable al editar
 *      (resources/views/modules/tenancy/admin/proyectos.blade.php:108) y
 *      `guardar()` escribe `mandante_id` en el UPDATE de edición
 *      (AdminProyectos.php:167): un proyecto puede cambiar de empresa cliente
 *      con dos clics, arrastrando carteras, personas y casos.
 *   2. `guardar()` valida SOLO el mandante DESTINO — llama a
 *      `guardContraMandanteAjeno($this->form['mandante_id'])`
 *      (AdminProyectos.php:114-115) — y nunca el de ORIGEN del proyecto que
 *      `editandoId` señala. Un admin de A puede fijar `editandoId` a un
 *      proyecto de B y apropiárselo.
 *
 * Y faltaba una tercera cosa, ya cerrada: archivar (D2). Antes sólo estaba
 * `desactivar()`, que pausa el proyecto pero lo deja a la vista de toda la
 * administración; ahora `archivar()` lo retira de los tres caminos por los que
 * se alcanza un proyecto.
 *
 * Los tests que fijan comportamiento correcto (listado scoped, guards de
 * abrirFormEditar/desactivar/activar, buscador, no-admins) están aquí para que
 * ese blindaje no se pierda al arreglar lo demás.
 */
final class FugaAdminProyectosTest extends TestCase
{
    use EscenarioMultiMandante;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    // ---------------------------------------------------------------
    // Listado: lo que ya filtra bien (fijamos el comportamiento)
    // ---------------------------------------------------------------

    public function test_admin_de_a_no_ve_los_proyectos_de_b(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $componente = Livewire::actingAs($a['adminMandante'])->test(AdminProyectos::class);

        $ids = $this->idsDe($componente->viewData('proyectos'));

        $this->assertContains((int) $a['proyecto']->id, $ids, 'El admin debe ver el proyecto de su propio mandante.');
        $this->assertNotContains((int) $b['proyecto']->id, $ids, 'FUGA: el proyecto del otro mandante aparece en el listado.');

        $this->assertNoSeFiltra($componente->html(), $b, 'AdminProyectos (listado)');
    }

    /**
     * El buscador es la puerta trasera clásica de un listado scoped: el WHERE
     * del texto se aplica DESPUÉS del scope, así que buscar por el código del
     * mandante ajeno no puede devolver nada.
     */
    public function test_el_buscador_no_alcanza_los_proyectos_del_otro_mandante(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        foreach (['código del mandante' => $b['mandante']->codigo, 'código del proyecto' => $b['proyecto']->codigo] as $que => $aguja) {
            $componente = Livewire::actingAs($a['adminMandante'])
                ->test(AdminProyectos::class)
                ->set('busqueda', (string) $aguja);

            $this->assertSame(
                [],
                $this->idsDe($componente->viewData('proyectos')),
                "FUGA: buscar por el {$que} del otro mandante devuelve resultados."
            );

            $this->assertNoSeFiltra($componente->html(), $b, "AdminProyectos (buscando el {$que} ajeno)");
        }
    }

    /**
     * El middleware `admin.dual` protege la RUTA, no el componente. Un
     * SUPERVISOR o un GESTOR montando el componente directamente no administra
     * ningún mandante: su listado tiene que salir vacío.
     */
    public function test_supervisor_y_gestor_no_ven_ningun_proyecto_en_la_pantalla_de_administracion(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        foreach (['supervisor', 'gestor'] as $perfil) {
            $componente = Livewire::actingAs($a[$perfil])->test(AdminProyectos::class);

            $this->assertSame(
                [],
                $this->idsDe($componente->viewData('proyectos')),
                "FUGA: el {$perfil} no administra mandante alguno y /admin/proyectos le lista proyectos."
            );

            $this->assertNoSeFiltra($componente->html(), $b, "AdminProyectos visto por el {$perfil} de alfa");
        }
    }

    public function test_admin_de_a_solo_puede_elegir_su_propio_mandante_al_crear(): void
    {
        ['a' => $a] = $this->montarDosMandantes();

        $componente = Livewire::actingAs($a['adminMandante'])
            ->test(AdminProyectos::class)
            ->call('abrirFormCrear');

        $idsMandantes = $this->idsDe($componente->viewData('mandantes'));

        $this->assertSame([(int) $a['mandante']->id], $idsMandantes, 'El desplegable de mandantes debe traer solo el propio.');
        $this->assertSame((int) $a['mandante']->id, (int) $componente->get('form.mandante_id'));
    }

    public function test_admin_de_a_no_puede_crear_un_proyecto_dentro_del_mandante_b(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        Livewire::actingAs($a['adminMandante'])
            ->test(AdminProyectos::class)
            ->call('abrirFormCrear')
            ->set('form.mandante_id', (int) $b['mandante']->id)
            ->set('form.codigo', 'INTRUSO_A_EN_B')
            ->set('form.nombre', 'Proyecto colado en B')
            ->set('form.tipo_operacion', 'cobranza')
            ->call('guardar')
            ->assertForbidden();

        $this->assertDatabaseMissing('proyectos', [
            'mandante_id' => $b['mandante']->id,
            'codigo' => 'INTRUSO_A_EN_B',
        ]);
    }

    // ---------------------------------------------------------------
    // Acciones sobre un id ajeno
    // ---------------------------------------------------------------

    public function test_admin_de_a_no_puede_abrir_el_formulario_de_un_proyecto_de_b(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        Livewire::actingAs($a['adminMandante'])
            ->test(AdminProyectos::class)
            ->call('abrirFormEditar', (int) $b['proyecto']->id)
            ->assertForbidden();
    }

    public function test_admin_de_a_no_puede_desactivar_ni_activar_un_proyecto_de_b(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        Livewire::actingAs($a['adminMandante'])
            ->test(AdminProyectos::class)
            ->call('desactivar', (int) $b['proyecto']->id)
            ->assertForbidden();

        $this->assertTrue(
            (bool) DB::table('proyectos')->where('id', $b['proyecto']->id)->value('activo'),
            'FUGA: el proyecto del otro mandante quedó desactivado.'
        );

        DB::table('proyectos')->where('id', $b['proyecto']->id)->update(['activo' => false]);

        Livewire::actingAs($a['adminMandante'])
            ->test(AdminProyectos::class)
            ->call('activar', (int) $b['proyecto']->id)
            ->assertForbidden();

        $this->assertFalse(
            (bool) DB::table('proyectos')->where('id', $b['proyecto']->id)->value('activo'),
            'FUGA: el proyecto del otro mandante quedó activado desde fuera.'
        );
    }

    // ---------------------------------------------------------------
    // El proyecto NO puede cambiar de mandante desde esta pantalla
    // ---------------------------------------------------------------

    /**
     * El selector de mandante es un campo de creación, no de edición: mover un
     * proyecto de empresa cliente se lleva por delante sus carteras, personas y
     * casos. Debe quedar bloqueado igual que `tipo_operacion` (§1.2), que la
     * vista ya renderiza como badge estático cuando `editandoId !== null`.
     *
     * La aguja es una expresión regular sobre `wire:model*="form.mandante_id"`
     * para que un cambio cosmético (`wire:model.live`, `wire:model.blur`) no
     * ponga el test en verde sin haber cerrado la puerta.
     */
    public function test_el_selector_de_mandante_no_es_editable_al_editar_un_proyecto(): void
    {
        ['a' => $a] = $this->montarDosMandantes();

        $aguja = '/wire:model[\w.:-]*=(["\'])form\.mandante_id\1/';

        $componente = Livewire::actingAs($a['adminMandante'])->test(AdminProyectos::class);

        $htmlCreando = $componente->call('abrirFormCrear')->html();
        $this->assertMatchesRegularExpression(
            $aguja,
            $htmlCreando,
            'Al CREAR sí debe poder elegirse el mandante; si esto falla, el test perdió su referencia.'
        );

        $htmlEditando = $componente->call('abrirFormEditar', (int) $a['proyecto']->id)->html();
        $this->assertDoesNotMatchRegularExpression(
            $aguja,
            $htmlEditando,
            'FUGA (resources/views/modules/tenancy/admin/proyectos.blade.php:108): el desplegable de mandante '
            .'sigue editable al editar; un proyecto puede cambiarse de empresa cliente desde la UI.'
        );
    }

    /**
     * Ni siquiera ADMIN_GLOBAL debe poder mover un proyecto de mandante desde
     * esta pantalla: `guardar()` escribe `mandante_id` en el UPDATE de edición
     * (AdminProyectos.php:167) sin comparar contra el mandante de origen.
     */
    public function test_ni_el_admin_global_puede_mover_un_proyecto_de_a_hacia_b(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        Livewire::actingAs($this->crearAdminGlobal())
            ->test(AdminProyectos::class)
            ->call('abrirFormEditar', (int) $a['proyecto']->id)
            ->set('form.mandante_id', (int) $b['mandante']->id)
            ->call('guardar');

        $this->assertSame(
            (int) $a['mandante']->id,
            (int) DB::table('proyectos')->where('id', $a['proyecto']->id)->value('mandante_id'),
            'FUGA: el proyecto cambió de mandante desde /admin/proyectos.'
        );
    }

    /**
     * El agujero que el guard de destino NO tapa para un ADMIN_MANDANTE: si el
     * mismo usuario administra dos mandantes (A y C), `guardContraMandanteAjeno`
     * aprueba el destino C y el UPDATE mueve el proyecto de A a C. No hace falta
     * ser ADMIN_GLOBAL para reasignar una empresa cliente entera.
     */
    public function test_un_admin_de_dos_mandantes_no_puede_mover_un_proyecto_entre_ellos(): void
    {
        ['a' => $a] = $this->montarDosMandantes();

        $mandanteC = $this->crearMandante('MND_GAMMA');
        $this->darRolAdminMandante($a['adminMandante'], $mandanteC);

        Livewire::actingAs($a['adminMandante'])
            ->test(AdminProyectos::class)
            ->call('abrirFormEditar', (int) $a['proyecto']->id)
            ->set('form.mandante_id', (int) $mandanteC->id)
            ->call('guardar');

        $this->assertSame(
            (int) $a['mandante']->id,
            (int) DB::table('proyectos')->where('id', $a['proyecto']->id)->value('mandante_id'),
            'FUGA: un ADMIN_MANDANTE con dos mandantes movió el proyecto de uno a otro; '
            .'guardar() solo valida el mandante destino, nunca el de origen.'
        );

        $this->assertSame(
            (int) $a['mandante']->id,
            $this->mandanteDelCaso((int) $a['casoId']),
            'FUGA: los casos siguieron al proyecto reasignado.'
        );
    }

    /**
     * La consecuencia real de mover un proyecto: la cartera, la persona y el
     * caso viajan con él a otra empresa cliente, sin traza ninguna.
     */
    public function test_mover_un_proyecto_arrastraria_sus_carteras_personas_y_casos_al_otro_mandante(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        Livewire::actingAs($this->crearAdminGlobal())
            ->test(AdminProyectos::class)
            ->call('abrirFormEditar', (int) $a['proyecto']->id)
            ->set('form.mandante_id', (int) $b['mandante']->id)
            ->call('guardar');

        $this->assertSame(
            (int) $a['mandante']->id,
            $this->mandanteDelCaso((int) $a['casoId']),
            'FUGA: el caso de A quedó colgando del mandante B tras mover el proyecto.'
        );

        $this->assertSame(
            (int) $a['mandante']->id,
            (int) DB::table('personas as pe')
                ->join('proyectos as p', 'p.id', '=', 'pe.proyecto_id')
                ->where('pe.id', $a['persona']->id)
                ->value('p.mandante_id'),
            'FUGA: la persona de A quedó bajo el mandante B tras mover el proyecto.'
        );

        $this->assertSame(
            (int) $a['mandante']->id,
            (int) DB::table('carteras as c')
                ->join('proyectos as p', 'p.id', '=', 'c.proyecto_id')
                ->where('c.id', $a['cartera']->id)
                ->value('p.mandante_id'),
            'FUGA: la cartera de A quedó bajo el mandante B tras mover el proyecto.'
        );
    }

    // ---------------------------------------------------------------
    // guardar() valida el mandante DESTINO, nunca el de ORIGEN
    // ---------------------------------------------------------------

    /**
     * El guard SÍ corta cuando el destino es ajeno: un admin de A que abre su
     * propio proyecto y apunta el desplegable a B recibe 403. Se fija aquí para
     * que el arreglo del origen no se lleve por delante la validación existente.
     */
    public function test_admin_de_a_no_puede_reasignar_su_propio_proyecto_al_mandante_b(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        Livewire::actingAs($a['adminMandante'])
            ->test(AdminProyectos::class)
            ->call('abrirFormEditar', (int) $a['proyecto']->id)
            ->set('form.mandante_id', (int) $b['mandante']->id)
            ->call('guardar')
            ->assertForbidden();

        $this->assertSame(
            (int) $a['mandante']->id,
            (int) DB::table('proyectos')->where('id', $a['proyecto']->id)->value('mandante_id'),
            'FUGA: el proyecto de A terminó bajo el mandante B.'
        );
    }

    /**
     * `guardar()` llama a `guardContraMandanteAjeno($this->form['mandante_id'])`
     * (AdminProyectos.php:115) — el destino. Nadie mira de quién era el
     * proyecto que `editandoId` señala. Un admin de A que apunte `editandoId`
     * a un proyecto de B y deje su propio mandante como destino pasa el guard
     * y se lleva el proyecto ajeno, con casos y personas incluidos.
     */
    public function test_admin_de_a_no_puede_apropiarse_de_un_proyecto_de_b_con_guardar(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        Livewire::actingAs($a['adminMandante'])
            ->test(AdminProyectos::class)
            ->set('editandoId', (int) $b['proyecto']->id)
            ->set('formVisible', true)
            ->set('form', [
                'mandante_id' => (int) $a['mandante']->id,
                'codigo' => (string) $b['proyecto']->codigo,
                'nombre' => 'Proyecto secuestrado',
                'descripcion' => '',
                'tipo_operacion' => (string) $b['proyecto']->tipo_operacion,
                'fecha_inicio' => null,
                'fecha_fin' => null,
            ])
            ->call('guardar');

        $this->assertSame(
            (int) $b['mandante']->id,
            (int) DB::table('proyectos')->where('id', $b['proyecto']->id)->value('mandante_id'),
            'FUGA: un admin del mandante A se apropió de un proyecto del mandante B vía guardar().'
        );

        $this->assertSame(
            (string) $b['proyecto']->nombre,
            (string) DB::table('proyectos')->where('id', $b['proyecto']->id)->value('nombre'),
            'FUGA: un admin del mandante A editó el nombre de un proyecto del mandante B.'
        );

        $this->assertSame(
            (int) $b['mandante']->id,
            $this->mandanteDelCaso((int) $b['casoId']),
            'FUGA: los casos del mandante B siguieron al proyecto secuestrado.'
        );
    }

    /**
     * Variante sin cambio de mandante: el admin de A apunta `editandoId` a un
     * proyecto de B y deja el mandante ORIGEN en el form. El destino coincide
     * con B, que le es ajeno, así que aquí el guard de destino sí debería
     * cortar — pero se comprueba también que el nombre no cambió, porque el
     * 403 llega DESPUÉS de la validación y antes del UPDATE.
     */
    public function test_admin_de_a_no_puede_editar_un_proyecto_de_b_dejando_su_mandante_intacto(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        Livewire::actingAs($a['adminMandante'])
            ->test(AdminProyectos::class)
            ->set('editandoId', (int) $b['proyecto']->id)
            ->set('formVisible', true)
            ->set('form', [
                'mandante_id' => (int) $b['mandante']->id,
                'codigo' => (string) $b['proyecto']->codigo,
                'nombre' => 'Renombrado por un extraño',
                'descripcion' => '',
                'tipo_operacion' => (string) $b['proyecto']->tipo_operacion,
                'fecha_inicio' => null,
                'fecha_fin' => null,
            ])
            ->call('guardar')
            ->assertForbidden();

        $this->assertSame(
            (string) $b['proyecto']->nombre,
            (string) DB::table('proyectos')->where('id', $b['proyecto']->id)->value('nombre'),
            'FUGA: un admin del mandante A renombró un proyecto del mandante B.'
        );
    }

    // ---------------------------------------------------------------
    // D2: archivar proyecto
    // ---------------------------------------------------------------

    /**
     * Un proyecto se retira, y retirarlo no es borrarlo. `archivar()` marca
     * `eliminada_en` y deja la fila donde estaba (§4): del `proyecto_id` cuelga
     * la operación entera, y las gestiones no se borran nunca (§13.11).
     *
     * Desde ese momento el proyecto ya no está en el listado de administración.
     * Que tampoco esté en los otros dos caminos —el selector y la URL— lo fijan
     * el test de más abajo y ArchivarProyectoTest.
     */
    public function test_existe_accion_de_archivar_proyecto_para_el_admin_del_mandante(): void
    {
        ['a' => $a] = $this->montarDosMandantes();

        $this->assertTrue(
            method_exists(AdminProyectos::class, 'archivar'),
            'D2: /admin/proyectos no ofrece archivar/borrar un proyecto — solo desactivar. '
            .'Falta la acción `archivar(int $id)` en AdminProyectos.'
        );

        $componente = Livewire::actingAs($a['adminMandante'])
            ->test(AdminProyectos::class)
            ->call('archivar', (int) $a['proyecto']->id)
            ->assertOk();

        $this->assertNotNull(
            DB::table('proyectos')->where('id', $a['proyecto']->id)->value('eliminada_en'),
            'Archivar debe marcar `eliminada_en` (borrado lógico §4), no borrar físicamente.'
        );

        $this->assertNotNull(
            DB::table('proyectos')->where('id', $a['proyecto']->id)->first(),
            'Archivar NO puede borrar físicamente la fila (§4: borrado lógico).'
        );

        $this->assertNotContains(
            (int) $a['proyecto']->id,
            $this->idsDe($componente->viewData('proyectos')),
            'El proyecto archivado debe desaparecer del listado de administración.'
        );
    }

    /**
     * Archivar es irreversible desde la pantalla, así que pasa por la misma
     * guarda que el resto de acciones de aquí: el id lo pone el cliente y lo
     * único que decide es el dueño ACTUAL de la fila. Un admin de A que apunte
     * a un proyecto de B se lleva un 403 antes de tocar nada.
     */
    public function test_admin_de_a_no_puede_archivar_un_proyecto_de_b(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $this->assertTrue(
            method_exists(AdminProyectos::class, 'archivar'),
            'D2: falta la acción `archivar(int $id)` en AdminProyectos.'
        );

        Livewire::actingAs($a['adminMandante'])
            ->test(AdminProyectos::class)
            ->call('archivar', (int) $b['proyecto']->id)
            ->assertForbidden();

        $this->assertNull(
            DB::table('proyectos')->where('id', $b['proyecto']->id)->value('eliminada_en'),
            'FUGA: un admin del mandante A archivó un proyecto del mandante B.'
        );
    }

    /**
     * Lo archivado desaparece también para quien trabajaba dentro: el selector
     * deja de ofrecerlo aunque la asignación en `usuario_proyecto_rol` siga
     * intacta. Y sólo desaparece él — los demás proyectos del mandante siguen
     * ahí, que archivar uno no es cerrarle la puerta al cliente.
     */
    public function test_proyecto_archivado_es_invisible_para_supervisor_y_gestor(): void
    {
        ['a' => $a] = $this->montarDosMandantes();

        // Segundo proyecto del mismo mandante: garantiza que el selector no
        // auto-redirija por tener un único proyecto accesible (SelectorProyecto::mount).
        $otro = $this->crearProyectoCobranza($a['mandante']);
        $this->darAccesoAProyecto($a['supervisor'], $otro, 'SUPERVISOR');
        $this->darAccesoAProyecto($a['gestor'], $otro, 'GESTOR');

        $this->assertTrue(
            method_exists(AdminProyectos::class, 'archivar'),
            'D2: falta la acción `archivar(int $id)` en AdminProyectos.'
        );

        Livewire::actingAs($a['adminMandante'])
            ->test(AdminProyectos::class)
            ->call('archivar', (int) $a['proyecto']->id)
            ->assertOk();

        foreach (['supervisor', 'gestor'] as $perfil) {
            $componente = Livewire::actingAs($a[$perfil])->test(SelectorProyecto::class);
            $ids = $this->idsDe($componente->viewData('proyectos'));

            $this->assertNotContains(
                (int) $a['proyecto']->id,
                $ids,
                "El proyecto archivado sigue visible para el {$perfil}."
            );
            $this->assertContains(
                (int) $otro->id,
                $ids,
                "Archivar un proyecto no debe ocultarle al {$perfil} los demás del mandante."
            );
        }
    }

    // ---------------------------------------------------------------
    // Helpers locales
    // ---------------------------------------------------------------

    private function mandanteDelCaso(int $casoId): int
    {
        return (int) DB::table('casos as c')
            ->join('proyectos as p', 'p.id', '=', 'c.proyecto_id')
            ->where('c.id', $casoId)
            ->value('p.mandante_id');
    }

    private function darAccesoAProyecto(User $usuario, stdClass $proyecto, string $codigoRol): void
    {
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $usuario->id,
            'proyecto_id' => $proyecto->id,
            'rol_id' => (int) DB::table('roles')->where('codigo', $codigoRol)->value('id'),
            'activo' => true,
        ]);
    }

    private function darRolAdminMandante(User $usuario, stdClass $mandante): void
    {
        DB::table('usuario_mandante_rol')->insert([
            'usuario_id' => $usuario->id,
            'mandante_id' => $mandante->id,
            'rol_id' => (int) DB::table('roles')->where('codigo', 'ADMIN_MANDANTE')->value('id'),
            'activo' => true,
        ]);
    }
}
