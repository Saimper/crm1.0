<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Asignaciones;

use App\Modules\Asignaciones\Application\UseCases\AutoasignarCaso;
use App\Modules\Asignaciones\Application\UseCases\ReasignarAsignacionAUsuario;
use App\Modules\Asignaciones\Domain\Exceptions\AutoasignacionNoPermitida;
use App\Modules\Asignaciones\Domain\Exceptions\TransicionAsignacionInvalida;
use App\Modules\Asignaciones\Infrastructure\Http\Livewire\Bandeja;
use App\Modules\Asignaciones\Infrastructure\Http\Livewire\BandejaEquipo;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\EscenarioMultiMandante;
use Tests\TestCase;

/**
 * El asesor toma la cuenta que va a trabajar; el supervisor se la pasa a otro.
 *
 * Con 5.000 cuentas en el proyecto y el reparto por lotes como única puerta, el
 * asesor que empezaba a gestionar una cuenta suelta no tenía forma de decir que
 * era suya hasta después de gestionarla.
 */
final class TomarYReasignarCuentaTest extends TestCase
{
    use EscenarioMultiMandante;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** @return array<string, mixed> */
    private function escenario(bool $permite = true): array
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        DB::table('proyectos')->where('id', $proyecto->id)->update(['permite_autoasignacion' => $permite]);

        $cartera = $this->crearCarteraEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto);
        $gestor = $this->crearGestor($proyecto);
        $supervisor = $this->crearSupervisor($proyecto);

        $casoId = (int) DB::table('casos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'cartera_id' => $cartera->id,
            'persona_id' => $persona->id,
            'tipo_caso' => 'cobranza',
            'estado_caso_id' => $estado->id,
            'fecha_ingreso' => '2026-09-01',
        ]);

        return compact('proyecto', 'casoId', 'persona', 'gestor', 'supervisor');
    }

    private function tomar(array $ctx, ?int $usuarioId = null): int
    {
        return $this->app->make(AutoasignarCaso::class)->execute(
            (int) $ctx['proyecto']->id,
            $ctx['casoId'],
            $usuarioId ?? (int) $ctx['gestor']->id,
            new DateTimeImmutable,
        );
    }

    public function test_el_asesor_toma_una_cuenta_sin_duenio(): void
    {
        $ctx = $this->escenario();

        $this->tomar($ctx);

        $this->assertDatabaseHas('asignaciones', [
            'caso_id' => $ctx['casoId'],
            'usuario_id' => (int) $ctx['gestor']->id,
            'estado' => 'pendiente',
        ]);
    }

    public function test_si_el_proyecto_no_lo_permite_no_se_toma(): void
    {
        $ctx = $this->escenario(permite: false);

        $this->expectException(AutoasignacionNoPermitida::class);
        $this->tomar($ctx);
    }

    public function test_una_cuenta_con_duenio_no_se_le_quita_a_su_asesor(): void
    {
        $ctx = $this->escenario();
        $this->tomar($ctx);

        $otro = $this->crearGestor($ctx['proyecto']);

        $this->expectException(AutoasignacionNoPermitida::class);
        $this->tomar($ctx, (int) $otro->id);
    }

    /**
     * El único `(proyecto_id, caso_id)` impide crear una segunda asignación
     * para la misma cuenta, así que una cuenta ya trabajada no vuelve al montón
     * sola: la devuelve el supervisor reasignándola.
     */
    public function test_una_cuenta_ya_trabajada_no_se_vuelve_a_tomar(): void
    {
        $ctx = $this->escenario();
        $asignacionId = $this->tomar($ctx);

        DB::table('asignaciones')->where('id', $asignacionId)->update([
            'estado' => 'cerrada',
            'cerrada_en' => now(),
        ]);

        $otro = $this->crearGestor($ctx['proyecto']);

        $this->expectException(AutoasignacionNoPermitida::class);
        $this->tomar($ctx, (int) $otro->id);
    }

    public function test_el_supervisor_pasa_la_cuenta_a_otro_asesor(): void
    {
        $ctx = $this->escenario();
        $asignacionId = $this->tomar($ctx);
        $otro = $this->crearGestor($ctx['proyecto']);

        $this->app->make(ReasignarAsignacionAUsuario::class)
            ->execute((int) $ctx['proyecto']->id, $asignacionId, (int) $otro->id);

        $this->assertDatabaseHas('asignaciones', [
            'id' => $asignacionId,
            'usuario_id' => (int) $otro->id,
        ]);
    }

    public function test_una_cuenta_en_trabajo_tambien_se_reasigna(): void
    {
        $ctx = $this->escenario();
        $asignacionId = $this->tomar($ctx);
        DB::table('asignaciones')->where('id', $asignacionId)->update(['estado' => 'en_trabajo']);
        $otro = $this->crearGestor($ctx['proyecto']);

        $this->app->make(ReasignarAsignacionAUsuario::class)
            ->execute((int) $ctx['proyecto']->id, $asignacionId, (int) $otro->id);

        $this->assertDatabaseHas('asignaciones', ['id' => $asignacionId, 'usuario_id' => (int) $otro->id]);
    }

    /**
     * Pasarle a alguien una cuenta cerrada la REABRE, y la pantalla lo dice con
     * otras palabras que una reasignación normal.
     *
     * Es la única puerta de vuelta que tiene una cuenta ya trabajada: desde que
     * el único es `(proyecto_id, caso_id)`, su fila cerrada es la única que
     * puede existir, así que mientras esté ahí no la toma nadie ni entra en el
     * reparto. Antes había un escape —crear una campaña nueva la devolvía a la
     * circulación—; al retirar la campaña, esto es lo que lo sustituye.
     */
    public function test_pasar_una_cuenta_cerrada_la_reabre(): void
    {
        $ctx = $this->escenario();
        $asignacionId = $this->tomar($ctx);
        DB::table('asignaciones')->where('id', $asignacionId)->update([
            'estado' => 'cerrada',
            'cerrada_en' => now(),
        ]);
        $otro = $this->crearGestor($ctx['proyecto']);

        $this->actingAs($ctx['supervisor']);
        $this->app->instance('tenancy.proyecto_activo', $ctx['proyecto']);

        Livewire::test(BandejaEquipo::class)
            ->call('reasignar', $asignacionId, (int) $otro->id)
            ->assertHasNoErrors()
            ->assertSee(__('asignaciones.reopen_done', ['usuario' => $otro->name]));

        $fila = DB::table('asignaciones')->where('id', $asignacionId)->first();

        $this->assertSame((int) $otro->id, (int) $fila->usuario_id);
        $this->assertSame('pendiente', (string) $fila->estado);
        $this->assertNull($fila->cerrada_en);
    }

    public function test_no_se_reasigna_a_quien_no_opera_en_el_proyecto(): void
    {
        $ctx = $this->escenario();
        $asignacionId = $this->tomar($ctx);

        $ajeno = $this->crearGestor($this->crearProyectoCobranza($this->crearMandante()));

        $this->expectException(TransicionAsignacionInvalida::class);
        $this->app->make(ReasignarAsignacionAUsuario::class)
            ->execute((int) $ctx['proyecto']->id, $asignacionId, (int) $ajeno->id);
    }

    /**
     * Multi-tenancy: una asignación de otro proyecto no se toca aunque se
     * conozca su id.
     */
    public function test_no_se_reasigna_una_asignacion_de_otro_proyecto(): void
    {
        $ctx = $this->escenario();
        $asignacionId = $this->tomar($ctx);

        $otroProyecto = $this->crearProyectoCobranza($this->crearMandante());
        $otroGestor = $this->crearGestor($otroProyecto);

        $this->expectException(TransicionAsignacionInvalida::class);
        $this->app->make(ReasignarAsignacionAUsuario::class)
            ->execute((int) $otroProyecto->id, $asignacionId, (int) $otroGestor->id);
    }

    public function test_la_bandeja_ofrece_las_cuentas_sin_duenio_y_deja_tomarlas(): void
    {
        $ctx = $this->escenario();
        $this->actingAs($ctx['gestor']);
        $this->app->instance('tenancy.proyecto_activo', $ctx['proyecto']);

        Livewire::test(Bandeja::class)
            ->set('estadoFiltro', 'sin_duenio')
            ->assertSee($ctx['persona']->identificacion)
            ->call('tomarCuenta', $ctx['casoId']);

        $this->assertDatabaseHas('asignaciones', [
            'caso_id' => $ctx['casoId'],
            'usuario_id' => (int) $ctx['gestor']->id,
        ]);
    }

    public function test_el_supervisor_reasigna_desde_la_bandeja_del_equipo(): void
    {
        $ctx = $this->escenario();
        $asignacionId = $this->tomar($ctx);
        $otro = $this->crearGestor($ctx['proyecto']);

        $this->actingAs($ctx['supervisor']);
        $this->app->instance('tenancy.proyecto_activo', $ctx['proyecto']);

        Livewire::test(BandejaEquipo::class)
            ->call('reasignar', $asignacionId, (int) $otro->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('asignaciones', [
            'id' => $asignacionId,
            'usuario_id' => (int) $otro->id,
        ]);
    }

    public function test_un_gestor_no_puede_reasignar(): void
    {
        $ctx = $this->escenario();
        $asignacionId = $this->tomar($ctx);
        $otro = $this->crearGestor($ctx['proyecto']);

        $this->assertFalse(
            $ctx['gestor']->tienePermiso('asignaciones.reasignar', (int) $ctx['proyecto']->id)
        );
        $this->assertTrue(
            $ctx['gestor']->tienePermiso('asignaciones.autoasignarse', (int) $ctx['proyecto']->id)
        );
        $this->assertDatabaseHas('asignaciones', [
            'id' => $asignacionId,
            'usuario_id' => (int) $ctx['gestor']->id,
        ]);
        $this->assertNotSame((int) $otro->id, (int) $ctx['gestor']->id);
    }
}
