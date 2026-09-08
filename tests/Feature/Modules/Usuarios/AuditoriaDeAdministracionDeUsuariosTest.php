<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Usuarios;

use App\Modules\Auditoria\Infrastructure\Http\Livewire\ListadoAuditoria;
use App\Modules\Usuarios\Infrastructure\Http\Livewire\AdminUsuarios;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioMultiMandante;
use Tests\TestCase;

/**
 * Lo que /admin/usuarios deja escrito en `auditorias`.
 *
 * Las dos acciones más sensibles de la aplicación viven en esta pantalla —dar
 * de alta una cuenta y darle acceso a los datos de un cliente— y hasta ahora
 * ninguna dejaba rastro: las cuentas se escribían con un modelo que nadie
 * observaba y los roles con pivotes que ningún observer Eloquent ve pasar.
 *
 * Estos tests cubren las dos mitades de la garantía: que el rastro EXISTE, y
 * que no lleva de más. En una tabla que guarda para siempre lo que pasó, meter
 * el hash de una contraseña es un daño que ya no se puede deshacer.
 */
final class AuditoriaDeAdministracionDeUsuariosTest extends TestCase
{
    use EscenarioMultiMandante;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    // ---------------------------------------------------------------------
    // Cuentas
    // ---------------------------------------------------------------------

    public function test_el_alta_de_una_cuenta_queda_registrada_con_quien_la_hizo(): void
    {
        $adminGlobal = $this->crearAdminGlobal();

        $this->crearDesdeLaPantalla($adminGlobal, 'alta.auditada@crm.local');

        $nuevoId = (int) DB::table('users')->where('email', 'alta.auditada@crm.local')->value('id');
        $evento = $this->eventoDe('users', $nuevoId, 'creado');

        $this->assertNotNull($evento, 'El alta de una cuenta no dejó fila en `auditorias`.');
        $this->assertSame((int) $adminGlobal->id, (int) $evento->usuario_id, 'El rastro no dice quién dio el alta.');

        $datos = $this->decodificar($evento->datos_despues);
        $this->assertSame('alta.auditada@crm.local', $datos['email']);
        $this->assertSame('Alta Auditada', $datos['name']);
        $this->assertTrue((bool) $datos['activo']);
    }

    public function test_el_rastro_del_alta_no_lleva_la_contrasena_ni_su_hash(): void
    {
        $adminGlobal = $this->crearAdminGlobal();

        $this->crearDesdeLaPantalla($adminGlobal, 'sin.secretos@crm.local', 'contrasena-en-claro');

        $nuevoId = (int) DB::table('users')->where('email', 'sin.secretos@crm.local')->value('id');
        $hash = (string) DB::table('users')->where('id', $nuevoId)->value('password');

        $registro = $this->auditoriaEnCrudo();

        $this->assertStringNotContainsString(
            'contrasena-en-claro',
            $registro,
            'La contraseña en claro acabó dentro de `auditorias`.'
        );
        $this->assertStringNotContainsString(
            $hash,
            $registro,
            'El hash de la contraseña acabó dentro de `auditorias`: un registro inmutable no es sitio para '
            .'material que se puede atacar sin prisa y sin conexión.'
        );
        $this->assertArrayNotHasKey(
            'password',
            $this->decodificar($this->eventoDe('users', $nuevoId, 'creado')?->datos_despues),
            'El snapshot del alta enumera la columna `password`.'
        );
    }

    public function test_editar_una_cuenta_registra_el_diff_del_correo(): void
    {
        ['a' => $a] = $this->montarDosMandantes();
        $adminGlobal = $this->crearAdminGlobal();
        $gestor = $a['gestor'];
        $correoPrevio = (string) $gestor->email;

        Livewire::actingAs($adminGlobal)
            ->test(AdminUsuarios::class)
            ->call('abrirFormEditarUsuario', (int) $gestor->id)
            ->set('formUsuario.email', 'otro.correo@crm.local')
            ->call('guardarUsuario')
            ->assertHasNoErrors();

        $cambios = $this->decodificar($this->eventoDe('users', (int) $gestor->id, 'actualizado')?->cambios);

        $this->assertArrayHasKey('email', $cambios, 'El cambio de correo no quedó en el diff.');
        $this->assertSame($correoPrevio, $cambios['email']['antes']);
        $this->assertSame('otro.correo@crm.local', $cambios['email']['despues']);
        $this->assertArrayNotHasKey('name', $cambios, 'El diff inventa cambios en campos que nadie tocó.');
    }

    public function test_el_cambio_de_contrasena_se_registra_como_hecho_y_nunca_como_valor(): void
    {
        ['a' => $a] = $this->montarDosMandantes();
        $adminGlobal = $this->crearAdminGlobal();
        $gestor = $a['gestor'];

        Livewire::actingAs($adminGlobal)
            ->test(AdminUsuarios::class)
            ->call('abrirFormEditarUsuario', (int) $gestor->id)
            ->set('formUsuario.password', 'la-nueva-de-otro')
            ->call('guardarUsuario')
            ->assertHasNoErrors();

        $cambios = $this->decodificar($this->eventoDe('users', (int) $gestor->id, 'actualizado')?->cambios);

        $this->assertArrayHasKey(
            'password',
            $cambios,
            'Que un administrador le cambie la contraseña a otro es justo lo que hay que poder reconstruir '
            .'después, y no quedó registrado.'
        );
        $this->assertNull($cambios['password']['antes']);

        $hash = (string) DB::table('users')->where('id', (int) $gestor->id)->value('password');
        $registro = $this->auditoriaEnCrudo();

        $this->assertStringNotContainsString('la-nueva-de-otro', $registro);
        $this->assertStringNotContainsString($hash, $registro);
    }

    public function test_guardar_sin_tocar_nada_no_inventa_un_evento(): void
    {
        $adminGlobal = $this->crearAdminGlobal();

        // La cuenta nace desde esta misma pantalla para que el correo ya venga
        // normalizado: `guardarUsuario` pasa el correo a minúsculas, así que
        // reguardar una cuenta con mayúsculas SÍ la cambia, y ese sí es un
        // evento que hay que registrar.
        $this->crearDesdeLaPantalla($adminGlobal, 'sin.cambios@crm.local');
        $usuarioId = (int) DB::table('users')->where('email', 'sin.cambios@crm.local')->value('id');

        Livewire::actingAs($adminGlobal)
            ->test(AdminUsuarios::class)
            ->call('abrirFormEditarUsuario', $usuarioId)
            ->call('guardarUsuario')
            ->assertHasNoErrors();

        $this->assertNull(
            $this->eventoDe('users', $usuarioId, 'actualizado'),
            'Abrir el formulario y guardarlo igual escribe un evento vacío: una auditoría llena de ruido es '
            .'una auditoría que nadie lee.'
        );
    }

    // ---------------------------------------------------------------------
    // Accesos
    // ---------------------------------------------------------------------

    public function test_conceder_un_rol_sobre_un_proyecto_queda_registrado_con_el_codigo_del_rol(): void
    {
        ['a' => $a] = $this->montarDosMandantes();
        $adminGlobal = $this->crearAdminGlobal();
        $gestor = $a['gestor'];

        $this->asignarDesdeLaPantalla($adminGlobal, (int) $gestor->id, $a['proyecto'], 'SUPERVISOR');

        $evento = $this->eventoDe('usuario_proyecto_rol', (int) $gestor->id, 'creado');

        $this->assertNotNull($evento, 'Conceder un rol sobre el proyecto de un cliente no dejó rastro.');
        $this->assertSame((int) $a['proyecto']->id, (int) $evento->proyecto_id);
        $this->assertSame(
            (int) $a['mandante']->id,
            (int) $evento->mandante_id,
            'El evento no quedó atribuido al cliente cuyo proyecto se abrió.'
        );

        $datos = $this->decodificar($evento->datos_despues);
        $this->assertSame(
            'SUPERVISOR',
            $datos['rol_codigo'],
            'El rastro guarda el id del rol pero no su código: dentro de un año ese número puede significar otra cosa.'
        );
        $this->assertSame((string) $a['proyecto']->codigo, $datos['proyecto_codigo']);
    }

    public function test_quitar_un_rol_de_proyecto_queda_registrado_con_lo_que_habia(): void
    {
        ['a' => $a] = $this->montarDosMandantes();
        $adminGlobal = $this->crearAdminGlobal();
        $gestor = $a['gestor'];
        $rolId = (int) DB::table('roles')->where('codigo', 'AUDITOR')->value('id');

        $this->asignarDesdeLaPantalla($adminGlobal, (int) $gestor->id, $a['proyecto'], 'AUDITOR');

        Livewire::actingAs($adminGlobal)
            ->test(AdminUsuarios::class)
            ->call('quitarAsignacion', (int) $gestor->id, (int) $a['proyecto']->id, $rolId);

        $evento = $this->eventoDe('usuario_proyecto_rol', (int) $gestor->id, 'eliminado');

        $this->assertNotNull($evento, 'Retirar un acceso es tan auditable como concederlo, y no dejó rastro.');
        $this->assertSame(
            'AUDITOR',
            $this->decodificar($evento->datos_antes)['rol_codigo'],
            'La auditoría es el único sitio donde queda lo que la fila borrada decía, y llegó vacío.'
        );
    }

    public function test_promover_y_revocar_el_rol_global_quedan_los_dos_registrados(): void
    {
        ['a' => $a] = $this->montarDosMandantes();
        $adminGlobal = $this->crearAdminGlobal();
        $gestor = $a['gestor'];

        Livewire::actingAs($adminGlobal)
            ->test(AdminUsuarios::class)
            ->call('promoverAdminGlobal', (int) $gestor->id)
            ->call('revocarAdminGlobal', (int) $gestor->id);

        $this->assertNotNull(
            $this->eventoDe('usuario_global_rol', (int) $gestor->id, 'creado'),
            'Abrir todos los clientes a la vez no dejó rastro.'
        );
        $this->assertNotNull(
            $this->eventoDe('usuario_global_rol', (int) $gestor->id, 'eliminado'),
            'Cerrar esa misma puerta tampoco dejó rastro.'
        );
    }

    // ---------------------------------------------------------------------
    // Aislamiento entre clientes (§12)
    // ---------------------------------------------------------------------

    public function test_el_rastro_de_un_acceso_lo_ve_el_admin_del_cliente_afectado_y_no_el_del_otro(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();
        $adminGlobal = $this->crearAdminGlobal();

        $this->asignarDesdeLaPantalla($adminGlobal, (int) $a['gestor']->id, $a['proyecto'], 'SUPERVISOR');

        $eventoId = (int) $this->eventoDe('usuario_proyecto_rol', (int) $a['gestor']->id, 'creado')->id;

        $this->assertContains(
            $eventoId,
            $this->registrosQueVe($a['adminMandante']),
            'El admin del cliente cuyo proyecto se abrió no ve en su auditoría que se abriera.'
        );
        $this->assertNotContains(
            $eventoId,
            $this->registrosQueVe($b['adminMandante']),
            'El admin de otro cliente ve un movimiento de accesos que no es suyo.'
        );
    }

    public function test_el_alta_hecha_dentro_de_un_cliente_queda_atribuida_a_ese_cliente(): void
    {
        ['a' => $a] = $this->montarDosMandantes();

        // Lo que publica el middleware `mandante.activo` en una petición real: el
        // cliente dentro del cual transcurre la pantalla.
        $this->app->instance('tenancy.mandante_activo', DB::table('mandantes')->find($a['mandante']->id));

        $this->crearDesdeLaPantalla($a['adminMandante'], 'nacido.en.alfa@crm.local');

        $nuevoId = (int) DB::table('users')->where('email', 'nacido.en.alfa@crm.local')->value('id');

        $this->assertSame(
            (int) $a['mandante']->id,
            (int) $this->eventoDe('users', $nuevoId, 'creado')->mandante_id,
            'Un alta hecha dentro de un cliente queda sin dueño: sin proyecto ni mandante, el evento no es '
            .'de nadie y sólo lo llega a ver ADMIN_GLOBAL.'
        );
    }

    // ---------------------------------------------------------------------
    // Utilidades
    // ---------------------------------------------------------------------

    private function crearDesdeLaPantalla(
        object $actor,
        string $email,
        string $password = 'contrasena-larga',
    ): void {
        Livewire::actingAs($actor)
            ->test(AdminUsuarios::class)
            ->call('abrirFormCrearUsuario')
            ->set('formUsuario.name', 'Alta Auditada')
            ->set('formUsuario.email', $email)
            ->set('formUsuario.password', $password)
            ->set('formUsuario.activo', true)
            ->call('guardarUsuario')
            ->assertHasNoErrors();
    }

    private function asignarDesdeLaPantalla(object $actor, int $usuarioId, stdClass $proyecto, string $rol): void
    {
        Livewire::actingAs($actor)
            ->test(AdminUsuarios::class)
            ->call('abrirFormAsignacion', $usuarioId)
            ->set('asignarProyectoId', (int) $proyecto->id)
            ->set('asignarRolId', (int) DB::table('roles')->where('codigo', $rol)->value('id'))
            ->call('guardarAsignacion')
            ->assertHasNoErrors();
    }

    private function eventoDe(string $entidadTipo, int $entidadId, string $evento): ?stdClass
    {
        return DB::table('auditorias')
            ->where('entidad_tipo', $entidadTipo)
            ->where('entidad_id', $entidadId)
            ->where('evento', $evento)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Los ids que un usuario alcanza en /admin/auditoria.
     *
     * `->items()` porque el listado pagina: un paginador convertido a array da
     * `data`, `current_page` y compañía, no las filas.
     *
     * @return list<int>
     */
    private function registrosQueVe(object $usuario): array
    {
        $registros = Livewire::actingAs($usuario)
            ->test(ListadoAuditoria::class)
            ->viewData('registros');

        return $this->idsDe($registros->items());
    }

    /**
     * La tabla entera como texto, para poder afirmar que algo NO está en
     * ninguna columna: ni en el snapshot, ni en el diff, ni en un sitio donde no
     * se nos ocurrió mirar.
     */
    private function auditoriaEnCrudo(): string
    {
        return (string) json_encode(DB::table('auditorias')->get()->all(), JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodificar(mixed $columna): array
    {
        $datos = json_decode((string) $columna, true);

        return is_array($datos) ? $datos : [];
    }
}
