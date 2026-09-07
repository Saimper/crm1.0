<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Auditoria\Infrastructure\Http\Livewire\ListadoAuditoria;
use App\Modules\Usuarios\Infrastructure\Http\Livewire\AdminUsuarios;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\EscenarioMultiMandante;
use Tests\TestCase;

/**
 * Red de fugas — superficie /admin/auditoria (`ListadoAuditoria`) y la
 * exportación CSV (`ExportarAuditoriaController`).
 *
 * La auditoría es el único sitio donde un cliente puede comprobar quién tocó
 * sus datos. Estado real de la superficie, comprobado contra el código:
 *
 *  1. El listado SÍ recorta por mandante en modo global (F39,
 *     ListadoAuditoria.php:58-93): registros, tipos de entidad, desplegable de
 *     usuarios y modal de detalle pasan todos por `proyectosPermitidos`. Los
 *     tests de esa parte están en VERDE y existen para que siga así.
 *  2. La exportación NO es la misma pantalla. Sólo hay una ruta de export
 *     (`proyectos.auditoria.exportar`, routes/web.php:172) scoped a un
 *     `proyecto_id` suelto: lo que el admin del mandante VE en /admin/auditoria
 *     no lo puede DESCARGAR con ese recorte — el botón de exportar ni siquiera
 *     se pinta en modo global (listado-auditoria.blade.php:50). Y el permiso
 *     `auditoria.exportar`, que el seeder define y NIEGA a SUPERVISOR, no lo
 *     exige nadie: ruta y controlador se conforman con `auditoria.ver`.
 *  3. La tabla `auditorias` no tiene `mandante_id`. El mandante sólo se deduce
 *     saltando a `proyectos`, así que cualquier evento con `proyecto_id = NULL`
 *     (toda acción administrativa) queda huérfano: no es de nadie y no lo ve
 *     nadie salvo ADMIN_GLOBAL.
 *  4. Ninguna acción administrativa deja rastro. `User` no está en
 *     `AuditoriaServiceProvider::MODELOS_AUDITADOS` y las asignaciones se
 *     escriben con `DB::table(...)`, que no dispara observers.
 *
 * Los tests de los bloques 2 (parcial), 3 y 4 están escritos para FALLAR hoy.
 * Cada fallo es una fuga concreta y localizada.
 */
final class FugaAuditoriaTest extends TestCase
{
    use EscenarioMultiMandante;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    // ---------------------------------------------------------------------
    // 1. Listado /admin/auditoria — modo global (sin proyecto activo)
    // ---------------------------------------------------------------------

    #[Group('fuga-pendiente')]
    public function test_admin_mandante_no_ve_eventos_de_proyectos_de_otro_mandante(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $eventoA = $this->insertarEvento($a, 'casos');
        $eventoB = $this->insertarEvento($b, 'casos');

        $componente = Livewire::actingAs($a['adminMandante'])->test(ListadoAuditoria::class);

        $ids = $this->idsDe($componente->viewData('registros'));

        $this->assertContains(
            $eventoA,
            $ids,
            'El admin del mandante A no ve ni siquiera los eventos de su propio proyecto: '
            .'sin esto el assertNotContains de abajo no probaría nada.'
        );
        $this->assertNotContains(
            $eventoB,
            $ids,
            '/admin/auditoria expone al mandante A un evento del proyecto del mandante B.'
        );
    }

    public function test_el_render_del_listado_global_no_deja_rastro_del_otro_mandante(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $this->insertarEvento($a, 'casos');
        $this->insertarEvento($b, 'casos');

        $html = Livewire::actingAs($a['adminMandante'])
            ->test(ListadoAuditoria::class)
            ->html();

        // Sin esto, un render vacío o roto haría pasar el assertNoSeFiltra en falso.
        $this->assertStringContainsString(
            (string) $a['proyecto']->codigo,
            $html,
            'El listado no pintó ni el proyecto propio (columna proyecto_codigo, '
            .'listado-auditoria.blade.php:99): el escenario no está probando nada.'
        );

        $this->assertNoSeFiltra($html, $b, '/admin/auditoria (listado global)');
    }

    public function test_el_detalle_de_un_evento_ajeno_no_se_abre_por_id(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $eventoA = $this->insertarEvento($a, 'personas');
        $eventoB = $this->insertarEvento($b, 'personas');

        // Primero el propio: si `detalle` fuera siempre null, el assertNull de
        // abajo pasaría sin demostrar nada.
        $propio = Livewire::actingAs($a['adminMandante'])
            ->test(ListadoAuditoria::class)
            ->call('verDetalle', $eventoA);

        $this->assertNotNull(
            $propio->viewData('detalle'),
            'verDetalle() no abre ni el evento propio: el escenario no está probando nada.'
        );
        $this->assertSame($eventoA, (int) $propio->viewData('detalle')->id);

        // Y ahora el ajeno, conociendo sólo su id.
        $ajeno = Livewire::actingAs($a['adminMandante'])
            ->test(ListadoAuditoria::class)
            ->call('verDetalle', $eventoB);

        $this->assertNull(
            $ajeno->viewData('detalle'),
            'El modal de detalle sirve el evento completo (datos_antes/datos_despues) de un proyecto '
            .'del mandante B a un admin del mandante A que sólo conoce el id (IDOR).'
        );

        $this->assertNoSeFiltra($ajeno->html(), $b, '/admin/auditoria (modal de detalle)');
    }

    public function test_el_filtro_de_usuarios_no_lista_gente_del_otro_mandante(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $this->insertarEvento($a, 'gestiones');
        $this->insertarEvento($b, 'gestiones');

        $componente = Livewire::actingAs($a['adminMandante'])->test(ListadoAuditoria::class);

        $ids = $this->idsDe($componente->viewData('usuarios'));

        $this->assertContains(
            (int) $a['gestor']->id,
            $ids,
            'El desplegable no trae ni al gestor propio: el escenario no está probando nada.'
        );

        foreach (['gestor', 'supervisor', 'adminMandante'] as $perfil) {
            $this->assertNotContains(
                (int) $b[$perfil]->id,
                $ids,
                "El desplegable de usuarios de /admin/auditoria expone al {$perfil} del mandante ajeno."
            );
        }
    }

    public function test_un_usuario_sin_rol_de_mandante_no_ve_ningun_evento_en_el_listado_global(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $this->insertarEvento($a, 'casos');
        $this->insertarEvento($b, 'casos');

        // Un gestor no tiene fila en `usuario_mandante_rol`: sin contexto de
        // mandante, el listado global tiene que negar por defecto, no abrir.
        $componente = Livewire::actingAs($a['gestor'])->test(ListadoAuditoria::class);

        $this->assertSame(
            0,
            $componente->viewData('registros')->total(),
            'Un usuario sin rol de mandante entra en el listado global de auditoría y ve eventos: '
            .'el default de una pantalla sin contexto de tenant tiene que ser "nada", no "todo".'
        );
    }

    public function test_un_usuario_operativo_no_puede_abrir_la_ruta_admin_auditoria(): void
    {
        ['a' => $a] = $this->montarDosMandantes();

        $respuesta = $this->actingAs($a['gestor'])->get(route('admin.auditoria'));

        $this->assertSame(
            403,
            $respuesta->getStatusCode(),
            '/admin/auditoria (middleware admin.dual, routes/web.php:221) deja entrar a un usuario '
            .'operativo sin rol de mandante ni ADMIN_GLOBAL.'
        );
    }

    #[Group('fuga-pendiente')]
    public function test_el_listado_no_se_fia_del_proyecto_activo_sin_comprobar_el_permiso(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $eventoB = $this->insertarEvento($b, 'casos');

        // Defensa en profundidad: hoy el binding sólo lo crea ResolverProyectoActivo,
        // que valida el acceso antes de bindear. Pero ListadoAuditoria, una vez en
        // modo proyecto, sirve `where a.proyecto_id = <activo>` sin preguntar nunca
        // `tienePermiso('auditoria.ver', $proyectoId)`: el aislamiento del historial
        // completo de un cliente descansa entero en un middleware de otro módulo.
        $this->activarProyecto($b['proyecto']);

        $componente = Livewire::actingAs($a['supervisor'])->test(ListadoAuditoria::class);

        $this->assertNotContains(
            $eventoB,
            $this->idsDe($componente->viewData('registros')),
            'ListadoAuditoria sirve la auditoría del proyecto activo a un usuario que no tiene '
            .'`auditoria.ver` en él (ListadoAuditoria.php:96): el componente no comprueba nada, '
            .'confía en que el middleware ya lo hizo.'
        );
    }

    // ---------------------------------------------------------------------
    // 2. Exportación — tiene que aplicar el MISMO filtro que la pantalla
    // ---------------------------------------------------------------------

    public function test_la_exportacion_exige_el_permiso_auditoria_exportar(): void
    {
        ['a' => $a] = $this->montarDosMandantes();

        $this->insertarEvento($a, 'casos');

        $respuesta = $this->actingAs($a['supervisor'])->get(route('proyectos.auditoria.exportar', [
            'proyecto_id' => (int) $a['proyecto']->id,
        ]));

        $this->assertSame(
            403,
            $respuesta->getStatusCode(),
            'SUPERVISOR no tiene `auditoria.exportar` (database/seeders/Usuarios/RolPermisoSeeder.php:96) '
            .'y aun así se descarga la auditoría entera del proyecto: la ruta pide `can:auditoria.ver` '
            .'(routes/web.php:174) y el controlador comprueba `auditoria.ver` '
            .'(ExportarAuditoriaController.php:20). El permiso `auditoria.exportar` está seeded pero '
            .'no lo exige nadie en todo el código.'
        );
    }

    public function test_admin_mandante_no_puede_exportar_la_auditoria_de_un_proyecto_ajeno(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $this->insertarEvento($b, 'casos');

        $respuesta = $this->actingAs($a['adminMandante'])->get(route('proyectos.auditoria.exportar', [
            'proyecto_id' => (int) $b['proyecto']->id,
        ]));

        $this->assertSame(
            403,
            $respuesta->getStatusCode(),
            'El admin del mandante A descarga por URL la auditoría de un proyecto del mandante B.'
        );
    }

    public function test_la_exportacion_del_proyecto_propio_no_arrastra_eventos_del_otro_mandante(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $this->insertarEvento($a, 'casos');
        $this->insertarEvento($b, 'casos');

        $respuesta = $this->actingAs($a['adminMandante'])->get(route('proyectos.auditoria.exportar', [
            'proyecto_id' => (int) $a['proyecto']->id,
        ]));

        $this->assertSame(200, $respuesta->getStatusCode(), 'El admin de A no puede exportar su propio proyecto.');

        $csv = $respuesta->streamedContent();

        // Los códigos viajan dentro de datos_antes/datos_despues (el CSV no tiene
        // columna de proyecto): si no aparecen, el CSV vino vacío.
        $this->assertStringContainsString(
            (string) $a['proyecto']->codigo,
            $csv,
            'El CSV no trae ni los eventos propios: el escenario no está probando nada.'
        );
        $this->assertNoSeFiltra($csv, $b, 'CSV de /proyectos/{id}/auditoria/exportar');
    }

    public function test_la_exportacion_no_deja_ampliar_el_recorte_por_query_string(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $this->insertarEvento($a, 'casos');
        $this->insertarEvento($b, 'casos');

        // El gestor de B no tiene absolutamente nada que hacer en un CSV de A;
        // filtrar por su id no puede convertirse en una consulta cross-proyecto.
        $respuesta = $this->actingAs($a['adminMandante'])->get(route('proyectos.auditoria.exportar', [
            'proyecto_id' => (int) $a['proyecto']->id,
            'usuario_id' => (int) $b['gestor']->id,
            'entidad_tipo' => 'casos',
        ]));

        $this->assertSame(200, $respuesta->getStatusCode());

        $csv = $respuesta->streamedContent();

        $this->assertStringContainsString(
            'public_id',
            $csv,
            'La respuesta no es siquiera un CSV: el escenario no está probando nada.'
        );
        $this->assertNoSeFiltra(
            $csv,
            $b,
            'CSV de /proyectos/{id}/auditoria/exportar con usuario_id ajeno'
        );
    }

    public function test_existe_una_exportacion_global_con_el_mismo_scope_de_mandante_que_la_pantalla(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $this->insertarEvento($a, 'casos');
        $this->insertarEvento($b, 'casos');

        $this->assertTrue(
            Route::has('admin.auditoria.exportar'),
            'La pantalla /admin/auditoria filtra por mandante (ListadoAuditoria.php:58-93) pero no existe '
            .'ninguna exportación capaz de expresar ese mismo filtro: la única ruta de export es '
            .'`proyectos.auditoria.exportar` (routes/web.php:172), scoped a un `proyecto_id` suelto, y '
            .'en modo global el botón ni se pinta (listado-auditoria.blade.php:50). Lo que el admin del '
            .'mandante VE en pantalla no lo puede DESCARGAR con el mismo recorte.'
        );

        $respuesta = $this->actingAs($a['adminMandante'])->get(route('admin.auditoria.exportar'));

        $this->assertSame(
            200,
            $respuesta->getStatusCode(),
            'El export global de /admin/auditoria no responde al admin de mandante.'
        );
        $this->assertNoSeFiltra($respuesta->streamedContent(), $b, 'CSV de /admin/auditoria/exportar');
    }

    // ---------------------------------------------------------------------
    // 3. `auditorias` no sabe de qué mandante es cada evento
    // ---------------------------------------------------------------------

    public function test_la_tabla_auditorias_puede_atribuir_cada_evento_a_un_mandante(): void
    {
        $this->assertTrue(
            Schema::hasColumn('auditorias', 'mandante_id'),
            '`auditorias` no tiene `mandante_id` (migración '
            .'2026_04_24_110000_auditoria_create_auditorias_table.php). El mandante sólo se deduce '
            .'saltando a `proyectos`, así que: (a) el filtrado por cliente exige un IN de ids de '
            .'proyecto recalculado en cada render, (b) un evento con `proyecto_id = NULL` no pertenece a '
            .'nadie, y (c) si un proyecto se mueve de mandante, su historial de auditoría se reescribe solo.'
        );
    }

    #[Group('fuga-pendiente')]
    public function test_el_listado_permite_filtrar_por_mandante(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $eventoA = $this->insertarEvento($a, 'casos');
        $eventoB = $this->insertarEvento($b, 'casos');

        $this->assertTrue(
            property_exists(ListadoAuditoria::class, 'mandanteId'),
            'ListadoAuditoria no expone ningún filtro por mandante: sus filtros son entidadTipo, '
            .'usuarioId, evento, desde y hasta (ListadoAuditoria.php:20-30). Un ADMIN_GLOBAL que audita '
            .'una queja de un cliente no puede acotar la pantalla a ese cliente; tiene que ir proyecto '
            .'por proyecto.'
        );

        $componente = Livewire::actingAs($this->crearAdminGlobal())
            ->test(ListadoAuditoria::class)
            ->set('mandanteId', (int) $a['mandante']->id);

        $ids = $this->idsDe($componente->viewData('registros'));

        $this->assertContains($eventoA, $ids, 'Filtrando por el mandante A no aparecen sus propios eventos.');
        $this->assertNotContains($eventoB, $ids, 'Filtrando por el mandante A siguen apareciendo eventos del mandante B.');
    }

    #[Group('fuga-pendiente')]
    public function test_un_evento_sin_proyecto_sigue_siendo_visible_para_el_admin_de_su_mandante(): void
    {
        ['a' => $a] = $this->montarDosMandantes();

        $huerfano = (int) DB::table('auditorias')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => null,
            'usuario_id' => (int) $a['adminMandante']->id,
            'entidad_tipo' => 'users',
            'entidad_id' => (int) $a['gestor']->id,
            'evento' => 'actualizado',
            'creada_en' => Carbon::now(),
        ]);

        $componente = Livewire::actingAs($a['adminMandante'])->test(ListadoAuditoria::class);

        $this->assertContains(
            $huerfano,
            $this->idsDe($componente->viewData('registros')),
            'Una acción administrativa del propio admin del mandante A (evento con `proyecto_id = NULL`) '
            .'desaparece de su auditoría: ListadoAuditoria filtra con `whereIn(a.proyecto_id, ...)` '
            .'(ListadoAuditoria.php:91), que descarta los NULL. Sin `mandante_id` en `auditorias` no hay '
            .'forma de devolvérselo a su dueño, y sólo ADMIN_GLOBAL lo llega a ver.'
        );
    }

    // ---------------------------------------------------------------------
    // 4. Las acciones administrativas no dejan rastro
    // ---------------------------------------------------------------------

    #[Group('fuga-pendiente')]
    public function test_crear_un_usuario_desde_admin_usuarios_deja_rastro_en_auditoria(): void
    {
        $adminGlobal = $this->crearAdminGlobal();
        $this->assertAuditoriaVacia();

        Livewire::actingAs($adminGlobal)
            ->test(AdminUsuarios::class)
            ->call('abrirFormCrearUsuario')
            ->set('formUsuario.name', 'Usuario Recien Creado')
            ->set('formUsuario.email', 'recien.creado@crm.local')
            ->set('formUsuario.password', 'contrasena-larga')
            ->set('formUsuario.activo', true)
            ->call('guardarUsuario')
            ->assertHasNoErrors();

        $creado = DB::table('users')->where('email', 'recien.creado@crm.local')->first();
        $this->assertNotNull($creado, 'El usuario ni siquiera se creó: el escenario no prueba nada.');

        $this->assertTrue(
            $this->hayRastroDeUsuario((int) $creado->id, 'creado'),
            'Crear un usuario desde /admin/usuarios no deja ninguna fila en `auditorias`. `User` no está '
            .'en AuditoriaServiceProvider::MODELOS_AUDITADOS (líneas 28-42), así que el observer nunca se '
            .'dispara: el alta de una cuenta con acceso a datos de clientes es invisible para siempre.'
        );
    }

    #[Group('fuga-pendiente')]
    public function test_editar_un_usuario_desde_admin_usuarios_deja_rastro_en_auditoria(): void
    {
        ['a' => $a] = $this->montarDosMandantes();

        $adminGlobal = $this->crearAdminGlobal();
        $gestor = $a['gestor'];
        $this->assertAuditoriaVacia();

        Livewire::actingAs($adminGlobal)
            ->test(AdminUsuarios::class)
            ->call('abrirFormEditarUsuario', (int) $gestor->id)
            ->set('formUsuario.name', 'Nombre Cambiado A Mano')
            ->set('formUsuario.email', 'correo.cambiado@crm.local')
            ->call('guardarUsuario')
            ->assertHasNoErrors();

        $this->assertSame(
            'correo.cambiado@crm.local',
            (string) DB::table('users')->where('id', (int) $gestor->id)->value('email'),
            'La edición no llegó a persistir: el escenario no prueba nada.'
        );

        $this->assertTrue(
            $this->hayRastroDeUsuario((int) $gestor->id, 'actualizado'),
            'Editar un usuario desde /admin/usuarios no deja rastro en `auditorias`. Nadie puede '
            .'reconstruir quién cambió el correo (y por tanto el login, vía SSO) de una cuenta que '
            .'trabaja los datos de un cliente.'
        );
    }

    #[Group('fuga-pendiente')]
    public function test_asignar_un_rol_de_proyecto_desde_admin_usuarios_deja_rastro_en_auditoria(): void
    {
        ['a' => $a] = $this->montarDosMandantes();

        $adminGlobal = $this->crearAdminGlobal();
        $gestor = $a['gestor'];
        $proyectoId = (int) $a['proyecto']->id;
        $rolSupervisorId = (int) DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id');
        $this->assertAuditoriaVacia();

        Livewire::actingAs($adminGlobal)
            ->test(AdminUsuarios::class)
            ->call('abrirFormAsignacion', (int) $gestor->id)
            ->set('asignarProyectoId', $proyectoId)
            ->set('asignarRolId', $rolSupervisorId)
            ->call('guardarAsignacion')
            ->assertHasNoErrors();

        $this->assertTrue(
            DB::table('usuario_proyecto_rol')
                ->where('usuario_id', (int) $gestor->id)
                ->where('proyecto_id', $proyectoId)
                ->where('rol_id', $rolSupervisorId)
                ->exists(),
            'La asignación no se guardó: el escenario no prueba nada.'
        );

        $this->assertTrue(
            DB::table('auditorias')
                ->where('proyecto_id', $proyectoId)
                ->where('usuario_id', (int) $adminGlobal->id)
                ->exists(),
            'Dar a alguien el rol SUPERVISOR sobre un proyecto de un cliente no deja rastro en '
            .'`auditorias`: AdminUsuarios::guardarAsignacion() escribe con `DB::table(...)->upsert()` '
            .'(AdminUsuarios.php:210), que no dispara ningún observer Eloquent. La escalada de '
            .'privilegios dentro del mandante es el evento más sensible del sistema y no se registra.'
        );
    }

    #[Group('fuga-pendiente')]
    public function test_promover_a_admin_global_deja_rastro_en_auditoria(): void
    {
        ['a' => $a] = $this->montarDosMandantes();

        $adminGlobal = $this->crearAdminGlobal();
        $gestor = $a['gestor'];
        $this->assertAuditoriaVacia();

        Livewire::actingAs($adminGlobal)
            ->test(AdminUsuarios::class)
            ->call('promoverAdminGlobal', (int) $gestor->id);

        $rolAdminGlobalId = (int) DB::table('roles')->where('codigo', 'ADMIN_GLOBAL')->value('id');
        $this->assertTrue(
            DB::table('usuario_global_rol')
                ->where('usuario_id', (int) $gestor->id)
                ->where('rol_id', $rolAdminGlobalId)
                ->exists(),
            'La promoción no se guardó: el escenario no prueba nada.'
        );

        $this->assertTrue(
            DB::table('auditorias')->where('entidad_id', (int) $gestor->id)->exists(),
            'Convertir a un gestor de un cliente en ADMIN_GLOBAL —acceso a los datos de TODOS los '
            .'mandantes— no deja ni una fila en `auditorias`: AdminUsuarios::promoverAdminGlobal() '
            .'inserta con `DB::table(\'usuario_global_rol\')->insert()` (AdminUsuarios.php:136). Es la '
            .'única puerta que abre todos los tenants a la vez y se abre sin testigos.'
        );
    }

    // ---------------------------------------------------------------------
    // Utilidades
    // ---------------------------------------------------------------------

    /**
     * Nada en el escenario (seeders con WithoutModelEvents, inserts por
     * DB::table, usuarios sin observer) escribe en `auditorias`: si la tabla no
     * arranca vacía, las aserciones de rastro podrían pasar por casualidad.
     */
    private function assertAuditoriaVacia(): void
    {
        $this->assertSame(
            0,
            DB::table('auditorias')->count(),
            'El escenario ya trae filas en `auditorias`: las aserciones de rastro dejan de ser fiables.'
        );
    }

    private function hayRastroDeUsuario(int $usuarioId, string $evento): bool
    {
        return DB::table('auditorias')
            ->whereIn('entidad_tipo', ['users', User::class])
            ->where('entidad_id', $usuarioId)
            ->where('evento', $evento)
            ->exists();
    }

    /**
     * Un evento de auditoría del mandante dado, con sus códigos incrustados en
     * el snapshot para que cualquier fuga sea visible en el HTML (el modal
     * pinta datos_antes/datos_despues) o en el CSV (los exporta como columnas).
     *
     * @param  array<string, mixed>  $lado
     */
    private function insertarEvento(array $lado, string $entidadTipo): int
    {
        $snapshot = [
            'mandante_codigo' => (string) $lado['mandante']->codigo,
            'proyecto_codigo' => (string) $lado['proyecto']->codigo,
            'cartera_codigo' => (string) $lado['cartera']->codigo,
            'gestor_email' => (string) $lado['gestor']->email,
        ];

        return (int) DB::table('auditorias')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => (int) $lado['proyecto']->id,
            'usuario_id' => (int) $lado['gestor']->id,
            'entidad_tipo' => $entidadTipo,
            'entidad_id' => (int) $lado['casoId'],
            'evento' => 'actualizado',
            'datos_antes' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            'datos_despues' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            'cambios' => null,
            'ip' => '10.0.0.1',
            'creada_en' => Carbon::now(),
        ]);
    }
}
