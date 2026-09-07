<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Modules\Usuarios\Infrastructure\Http\Livewire\AdminUsuarios;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\EscenarioMultiMandante;
use Tests\TestCase;

/**
 * Red de fugas — superficie /admin/usuarios (`AdminUsuarios`).
 *
 * La queja literal del dueño: "el administrador ve todos los usuarios de todas
 * las empresas, y por error puedo eliminar un usuario que no pertenece".
 *
 * Lo que se encontró al leer el componente:
 *
 *  - El LISTADO sí filtra por mandante (F39): `render()` exige un pivot en un
 *    proyecto del mandante o en `usuario_mandante_rol`. Los tests de listado y
 *    buscador de aquí pasan hoy en verde; están para clavar ese comportamiento,
 *    no para denunciarlo.
 *  - Las ACCIONES son otra historia. `abrirFormEditarUsuario`, `guardarUsuario`
 *    y `abrirFormAsignacion` no miran a quién pertenece el id que reciben;
 *    `guardarAsignacion` y `quitarAsignacion` sólo validan el PROYECTO
 *    (`guardContraProyectoAjeno`), nunca el USUARIO. Un id ajeno viaja en el
 *    payload de un `wire:click`: es forjable.
 *  - No existe `eliminarUsuario`: "eliminar" en esta pantalla es desactivar
 *    (`formUsuario.activo = false` + `guardarUsuario`). Ahí muerde la queja.
 *  - Como no hay `mandante_activo`, un usuario creado desde esta pantalla no
 *    queda ligado a ningún cliente: nace huérfano y desaparece del listado de
 *    quien lo creó.
 *
 * Los tests de acción están escritos para FALLAR hoy. Cada fallo es una fuga.
 */
final class FugaAdminUsuariosTest extends TestCase
{
    use EscenarioMultiMandante;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    // ---------------------------------------------------------------------
    // Listado, buscador y asignaciones (hoy en verde: F39 los filtra)
    // ---------------------------------------------------------------------

    public function test_admin_mandante_no_ve_ningun_usuario_del_otro_mandante_en_el_listado(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $componente = Livewire::actingAs($a['adminMandante'])->test(AdminUsuarios::class);

        $ids = $this->idsDe($componente->viewData('usuarios'));

        foreach (['gestor', 'supervisor', 'adminMandante'] as $perfil) {
            $this->assertNotContains(
                (int) $b[$perfil]->id,
                $ids,
                "El listado de /admin/usuarios expone al {$perfil} del mandante ajeno."
            );
        }

        $this->assertContains((int) $a['gestor']->id, $ids, 'Debe ver a su propio gestor.');
        $this->assertContains((int) $a['supervisor']->id, $ids, 'Debe ver a su propio supervisor.');

        $this->assertNoSeFiltra($componente->html(), $b, '/admin/usuarios (listado + matriz de acceso)');
    }

    public function test_el_buscador_de_admin_usuarios_no_encuentra_por_correo_exacto_a_un_usuario_ajeno(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $componente = Livewire::actingAs($a['adminMandante'])
            ->test(AdminUsuarios::class)
            ->set('busqueda', $b['gestor']->email);

        $this->assertNotContains(
            (int) $b['gestor']->id,
            $this->idsDe($componente->viewData('usuarios')),
            'Buscar el correo exacto de un usuario ajeno lo devuelve: el buscador ignora el mandante.'
        );

        $this->assertStringNotContainsString(
            (string) $b['gestor']->email,
            $componente->html(),
            'El correo del usuario ajeno aparece en el HTML del resultado de búsqueda.'
        );
    }

    /**
     * Los gestores de ambos mandantes se llaman igual ("Gestor"): buscar por
     * nombre es el caso donde un filtro por `like` sin mandante se delata.
     */
    public function test_el_buscador_por_nombre_no_alcanza_a_los_homonimos_del_otro_mandante(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $componente = Livewire::actingAs($a['adminMandante'])
            ->test(AdminUsuarios::class)
            ->set('busqueda', (string) $b['gestor']->name);

        $ids = $this->idsDe($componente->viewData('usuarios'));

        $this->assertContains((int) $a['gestor']->id, $ids, 'Debe seguir encontrando a su propio gestor.');
        $this->assertNotContains(
            (int) $b['gestor']->id,
            $ids,
            'Buscar por nombre devuelve al homónimo del otro cliente.'
        );
    }

    public function test_la_matriz_de_asignaciones_no_trae_filas_de_proyectos_ajenos(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $componente = Livewire::actingAs($a['adminMandante'])->test(AdminUsuarios::class);

        /** @var Collection<int, mixed> $asignaciones */
        $asignaciones = $componente->viewData('asignaciones');

        $this->assertTrue(
            $asignaciones->has((int) $a['gestor']->id),
            'Deben verse las asignaciones de los usuarios propios.'
        );
        $this->assertFalse(
            $asignaciones->has((int) $b['gestor']->id),
            'La matriz de acceso trae las asignaciones de un usuario del otro mandante.'
        );

        $proyectosEnMatriz = (new Collection($asignaciones->flatten(1)))
            ->map(fn ($fila): int => (int) $fila->proyecto_id)
            ->unique()
            ->all();

        $this->assertNotContains(
            (int) $b['proyecto']->id,
            $proyectosEnMatriz,
            'La matriz de acceso incluye filas del proyecto de otro cliente.'
        );
    }

    /**
     * D1 — comportamiento ACTUAL que se quiere cambiar: el ADMIN_GLOBAL ve a los
     * usuarios de todos los clientes a la vez. Cuando D1 esté implementado (el
     * admin global opera dentro de un cliente a la vez) este test debe
     * reescribirse: es la foto del "antes", no un contrato deseado.
     */
    public function test_d1_a_cambiar_admin_global_hoy_ve_usuarios_de_todos_los_mandantes(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $componente = Livewire::actingAs($this->crearAdminGlobal())->test(AdminUsuarios::class);

        $ids = $this->idsDe($componente->viewData('usuarios'));

        $this->assertContains((int) $a['gestor']->id, $ids);
        $this->assertContains(
            (int) $b['gestor']->id,
            $ids,
            'Si esto falla, D1 ya se implementó: reescribe este test como aislamiento.'
        );

        $html = $componente->html();
        $this->assertStringContainsString((string) $a['gestor']->email, $html);
        $this->assertStringContainsString(
            (string) $b['gestor']->email,
            $html,
            'Foto del antes: una sola pantalla mezcla los correos de dos empresas cliente.'
        );
    }

    // ---------------------------------------------------------------------
    // Acciones sobre un id ajeno (el id viaja en el payload: es forjable)
    // ---------------------------------------------------------------------

    public function test_admin_mandante_no_puede_abrir_el_formulario_de_edicion_de_un_usuario_ajeno(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        Livewire::actingAs($a['adminMandante'])
            ->test(AdminUsuarios::class)
            ->call('abrirFormEditarUsuario', (int) $b['gestor']->id)
            ->assertForbidden();
    }

    public function test_abrir_edicion_de_usuario_ajeno_no_debe_volcar_sus_datos_en_el_formulario(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $componente = Livewire::actingAs($a['adminMandante'])
            ->test(AdminUsuarios::class)
            ->call('abrirFormEditarUsuario', (int) $b['gestor']->id);

        $this->assertNotSame(
            (string) $b['gestor']->email,
            (string) $componente->get('formUsuario.email'),
            'El drawer de edición carga los datos de un usuario de otro cliente.'
        );

        $this->assertNotSame(
            (int) $b['gestor']->id,
            (int) $componente->get('editandoUsuarioId'),
            'El componente queda apuntando a un usuario ajeno: el próximo guardarUsuario lo escribe.'
        );

        $this->assertNoSeFiltra($componente->html(), $b, '/admin/usuarios (drawer de edición)');
    }

    public function test_admin_mandante_no_puede_editar_ni_desactivar_a_un_usuario_ajeno(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $correoOriginal = (string) $b['gestor']->email;

        Livewire::actingAs($a['adminMandante'])
            ->test(AdminUsuarios::class)
            ->set('editandoUsuarioId', (int) $b['gestor']->id)
            ->set('formUsuario', [
                'name' => 'Secuestrado por Alfa',
                'email' => 'secuestrado.por.alfa@crm.local',
                'password' => '',
                'activo' => false,
            ])
            ->call('guardarUsuario');

        $this->assertDatabaseHas('users', [
            'id' => (int) $b['gestor']->id,
            'email' => $correoOriginal,
            'activo' => true,
        ]);

        $this->assertDatabaseMissing('users', ['email' => 'secuestrado.por.alfa@crm.local']);
        $this->assertDatabaseMissing('users', ['name' => 'Secuestrado por Alfa']);
    }

    public function test_admin_mandante_no_puede_cambiar_la_password_de_un_usuario_ajeno(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $hashPrevio = (string) DB::table('users')->where('id', $b['gestor']->id)->value('password');

        Livewire::actingAs($a['adminMandante'])
            ->test(AdminUsuarios::class)
            ->set('editandoUsuarioId', (int) $b['gestor']->id)
            ->set('formUsuario', [
                'name' => (string) $b['gestor']->name,
                'email' => (string) $b['gestor']->email,
                'password' => 'nuevaclave123',
                'activo' => true,
            ])
            ->call('guardarUsuario');

        $this->assertSame(
            $hashPrevio,
            (string) DB::table('users')->where('id', $b['gestor']->id)->value('password'),
            'Un admin de otra empresa reescribió la contraseña: puede suplantar al usuario.'
        );
    }

    public function test_admin_mandante_no_puede_abrir_la_asignacion_de_rol_de_un_usuario_ajeno(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        Livewire::actingAs($a['adminMandante'])
            ->test(AdminUsuarios::class)
            ->call('abrirFormAsignacion', (int) $b['gestor']->id)
            ->assertForbidden();
    }

    /**
     * El caso con daño real: el guard mira el PROYECTO, y el proyecto ES suyo.
     * Resultado: un usuario del cliente B entra al proyecto del cliente A.
     */
    public function test_admin_mandante_no_puede_asignar_a_un_usuario_ajeno_dentro_de_su_propio_proyecto(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $rolGestorId = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');

        Livewire::actingAs($a['adminMandante'])
            ->test(AdminUsuarios::class)
            ->set('usuarioAsignandoId', (int) $b['gestor']->id)
            ->set('asignarProyectoId', (int) $a['proyecto']->id)
            ->set('asignarRolId', $rolGestorId)
            ->call('guardarAsignacion');

        $this->assertDatabaseMissing('usuario_proyecto_rol', [
            'usuario_id' => (int) $b['gestor']->id,
            'proyecto_id' => (int) $a['proyecto']->id,
        ]);
    }

    public function test_admin_mandante_no_puede_quitar_asignaciones_pasando_el_id_de_un_usuario_ajeno(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $rolGestorId = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');

        Livewire::actingAs($a['adminMandante'])
            ->test(AdminUsuarios::class)
            ->call('quitarAsignacion', (int) $b['gestor']->id, (int) $a['proyecto']->id, $rolGestorId)
            ->assertForbidden();
    }

    /**
     * Contraste del test anterior: hoy el único guard existente es por PROYECTO
     * (`guardContraProyectoAjeno`). Con proyecto ajeno sí corta. Este test pasa
     * en verde y debe seguir pasando: documenta la forma del guard actual y por
     * qué no basta.
     */
    public function test_el_guard_actual_solo_corta_cuando_el_proyecto_es_ajeno(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $rolGestorId = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');

        Livewire::actingAs($a['adminMandante'])
            ->test(AdminUsuarios::class)
            ->call('quitarAsignacion', (int) $b['gestor']->id, (int) $b['proyecto']->id, $rolGestorId)
            ->assertForbidden();

        $this->assertDatabaseHas('usuario_proyecto_rol', [
            'usuario_id' => (int) $b['gestor']->id,
            'proyecto_id' => (int) $b['proyecto']->id,
            'rol_id' => $rolGestorId,
        ]);
    }

    public function test_admin_mandante_no_puede_promover_ni_revocar_admin_global_sobre_un_usuario_ajeno(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        Livewire::actingAs($a['adminMandante'])
            ->test(AdminUsuarios::class)
            ->call('promoverAdminGlobal', (int) $b['gestor']->id)
            ->assertForbidden();

        Livewire::actingAs($a['adminMandante'])
            ->test(AdminUsuarios::class)
            ->call('revocarAdminGlobal', (int) $b['gestor']->id)
            ->assertForbidden();

        $this->assertDatabaseMissing('usuario_global_rol', [
            'usuario_id' => (int) $b['gestor']->id,
        ]);
    }

    // ---------------------------------------------------------------------
    // Falta de contexto de mandante
    // ---------------------------------------------------------------------

    public function test_la_tabla_de_usuarios_debe_mostrar_a_que_mandante_pertenece_cada_usuario(): void
    {
        ['a' => $a] = $this->montarDosMandantes();

        $componente = Livewire::actingAs($a['adminMandante'])->test(AdminUsuarios::class);

        $fila = $componente->viewData('usuarios')->firstWhere('id', (int) $a['gestor']->id);

        $this->assertNotNull($fila, 'El gestor propio no aparece en el listado.');
        $this->assertTrue(
            property_exists($fila, 'mandante_codigo'),
            'La consulta de /admin/usuarios no expone el mandante de cada usuario: la tabla no puede decir de quién es.'
        );

        $this->assertStringContainsString(
            (string) $a['mandante']->codigo,
            $componente->html(),
            'La tabla de usuarios no muestra ninguna columna de mandante.'
        );
    }

    public function test_usuario_creado_por_un_admin_mandante_queda_ligado_a_su_mandante(): void
    {
        ['a' => $a] = $this->montarDosMandantes();

        $componente = Livewire::actingAs($a['adminMandante'])
            ->test(AdminUsuarios::class)
            ->set('formUsuario', [
                'name' => 'Nuevo de Alfa',
                'email' => 'nuevo.de.alfa@crm.local',
                'password' => 'secreto123',
                'activo' => true,
            ])
            ->call('guardarUsuario')
            ->assertHasNoErrors();

        $nuevoId = (int) DB::table('users')->where('email', 'nuevo.de.alfa@crm.local')->value('id');
        $this->assertGreaterThan(0, $nuevoId, 'El usuario no llegó a crearse.');

        $ligado = DB::table('usuario_mandante_rol')
            ->where('usuario_id', $nuevoId)
            ->where('mandante_id', (int) $a['mandante']->id)
            ->exists()
            || DB::table('usuario_proyecto_rol')
                ->where('usuario_id', $nuevoId)
                ->whereIn('proyecto_id', [(int) $a['proyecto']->id])
                ->exists();

        $this->assertTrue(
            $ligado,
            'El usuario nace huérfano: sin pivot de mandante ni de proyecto, no pertenece a ningún cliente.'
        );

        $this->assertContains(
            $nuevoId,
            $this->idsDe($componente->viewData('usuarios')),
            'El usuario recién creado desaparece del listado de quien lo creó.'
        );
    }
}
