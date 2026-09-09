<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Usuarios;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\EscenarioMultiMandante;
use Tests\TestCase;

/**
 * `User::tienePermiso()` y `esAdminGlobal()` recuerdan lo que ya contestaron.
 *
 * Medido antes de esto: 22 consultas para pintar los 6 enlaces del menú
 * lateral y 13 más por las tarjetas del dashboard, en cada carga de cada
 * página. Lo que estos tests fijan es tanto el ahorro como sus límites: el
 * memo se olvida cuando cambia el proyecto activo, cuando alguien escribe en
 * una tabla de roles y en cada petición HTTP.
 */
final class CachePermisosTest extends TestCase
{
    use EscenarioMultiMandante;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_la_segunda_comprobacion_con_los_mismos_argumentos_no_consulta_la_base(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);
        $this->activarProyecto($proyecto);

        $primera = $this->consultasAlEjecutar(fn (): bool => $gestor->tienePermiso('casos.ver'));
        self::assertGreaterThan(0, $primera, 'La primera comprobación tiene que ir a la base: si no, el test no mide nada.');

        $segunda = $this->consultasAlEjecutar(function () use ($gestor): void {
            self::assertTrue($gestor->tienePermiso('casos.ver'));
        });

        self::assertSame(0, $segunda);
    }

    public function test_la_clave_del_memo_lleva_el_proyecto_resuelto_y_no_el_argumento_nulo(): void
    {
        $mandante = $this->crearMandante();
        $proyectoA = $this->crearProyectoCobranza($mandante);
        $proyectoB = $this->crearProyectoCx($mandante);
        $gestor = $this->crearGestor($proyectoA);

        // Sin proyecto explícito: `Gate::before` pasa null en todo @can sin
        // argumentos, y aquí es el proyecto activo quien decide.
        $this->activarProyecto($proyectoA);
        self::assertTrue($gestor->tienePermiso('casos.ver'));

        $this->activarProyecto($proyectoB);
        self::assertFalse(
            $gestor->tienePermiso('casos.ver'),
            'Con el memo indexado por el null literal, la respuesta del proyecto A se serviría para el B.',
        );

        // Volver a A no vuelve a consultar: la entrada de A sigue en el memo.
        $this->activarProyecto($proyectoA);
        $consultas = $this->consultasAlEjecutar(function () use ($gestor): void {
            self::assertTrue($gestor->tienePermiso('casos.ver'));
        });

        self::assertSame(0, $consultas);
    }

    public function test_una_asignacion_escrita_con_db_table_se_ve_en_la_siguiente_comprobacion(): void
    {
        $mandante = $this->crearMandante();
        $proyectoA = $this->crearProyectoCobranza($mandante);
        $proyectoB = $this->crearProyectoCx($mandante);
        $gestor = $this->crearGestor($proyectoA);

        self::assertFalse($gestor->tienePermiso('casos.ver', (int) $proyectoB->id));

        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $gestor->id,
            'proyecto_id' => $proyectoB->id,
            'rol_id' => (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id'),
            'activo' => true,
        ]);

        self::assertTrue(
            $gestor->tienePermiso('casos.ver', (int) $proyectoB->id),
            'La escritura en usuario_proyecto_rol tiene que tirar el memo de la misma instancia.',
        );
    }

    public function test_desactivar_el_rol_de_mandante_retira_el_permiso_en_la_misma_instancia(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $admin = $this->crearAdminDeMandante($mandante);

        self::assertTrue($admin->tienePermiso('casos.ver', (int) $proyecto->id));

        DB::table('usuario_mandante_rol')
            ->where('usuario_id', $admin->id)
            ->where('mandante_id', $mandante->id)
            ->update(['activo' => false]);

        self::assertFalse($admin->tienePermiso('casos.ver', (int) $proyecto->id));
    }

    public function test_una_peticion_http_vacia_el_memo(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);
        $this->activarProyecto($proyecto);

        self::assertTrue($gestor->tienePermiso('casos.ver'));
        self::assertSame(0, $this->consultasAlEjecutar(fn (): bool => $gestor->tienePermiso('casos.ver')));

        // Una ruta que no existe: pasa por Kernel::handle —que rebindea
        // `request`— sin que ningún middleware vuelva a llenar el memo.
        $this->get('/no-existe')->assertNotFound();

        $consultas = $this->consultasAlEjecutar(function () use ($gestor): void {
            self::assertTrue($gestor->tienePermiso('casos.ver'));
        });

        self::assertGreaterThan(0, $consultas, 'Tras una petición el permiso se tiene que volver a leer de la base.');
    }

    public function test_olvidar_permisos_cacheados_fuerza_la_relectura(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        self::assertTrue($gestor->tienePermiso('casos.ver', (int) $proyecto->id));

        User::olvidarPermisosCacheados();

        $consultas = $this->consultasAlEjecutar(fn (): bool => $gestor->tienePermiso('casos.ver', (int) $proyecto->id));

        self::assertGreaterThan(0, $consultas);
    }

    public function test_el_camino_gate_before_tambien_queda_cacheado(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        self::assertTrue($gestor->can('casos.ver', (int) $proyecto->id));

        $consultas = $this->consultasAlEjecutar(function () use ($gestor, $proyecto): void {
            self::assertTrue($gestor->can('casos.ver', (int) $proyecto->id));
        });

        self::assertSame(
            0,
            $consultas,
            'Un segundo @can con los mismos argumentos no puede costar ni la consulta de esAdminGlobal().',
        );
    }

    public function test_es_admin_global_se_consulta_una_sola_vez_por_instancia(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);
        $this->activarProyecto($proyecto);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $gestor->esAdminGlobal();
        $gestor->tienePermiso('casos.ver');
        $gestor->tienePermiso('gestiones.crear');
        $gestor->tienePermiso('reportes.operativos');

        self::assertSame(1, $this->consultasQueNombran('usuario_global_rol', DB::getQueryLog()));
    }

    public function test_el_memo_del_admin_de_un_mandante_no_le_abre_los_proyectos_de_otro(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $admin = $a['adminMandante'];

        self::assertTrue($admin->tienePermiso('casos.ver', (int) $a['proyecto']->id));
        self::assertFalse(
            $admin->tienePermiso('casos.ver', (int) $b['proyecto']->id),
            'Lo cacheado para un proyecto del mandante A no puede autorizar un proyecto del mandante B.',
        );

        // Y al revés, sobre otra instancia: el memo es por usuario, no global.
        self::assertFalse($b['adminMandante']->tienePermiso('casos.ver', (int) $a['proyecto']->id));
        self::assertTrue($b['adminMandante']->tienePermiso('casos.ver', (int) $b['proyecto']->id));
    }

    /** Cuántas consultas ejecuta la acción dada. */
    private function consultasAlEjecutar(callable $accion): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $accion();

        return count(DB::getQueryLog());
    }

    /** @param  array<int, array{query: string}>  $log */
    private function consultasQueNombran(string $tabla, array $log): int
    {
        return count(array_filter($log, static fn (array $q): bool => str_contains($q['query'], $tabla)));
    }
}
