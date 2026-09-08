<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Compromisos;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * El ciclo de §6, cerrado.
 *
 * Antes existían las tres transiciones y una pantalla para dispararlas a mano,
 * pero nada convertía el paso del tiempo en un hecho: la promesa vencida se
 * quedaba `pendiente` indefinidamente y la bandera del caso seguía diciendo que
 * había un compromiso vigente.
 */
final class RomperCompromisosVencidosTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_rompe_lo_vencido_y_no_toca_lo_que_sigue_en_plazo(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $vencido = $this->crearCompromiso($proyecto, Carbon::today()->subDays(3));
        $vigente = $this->crearCompromiso($proyecto, Carbon::today()->addDays(3));
        $venceHoy = $this->crearCompromiso($proyecto, Carbon::today());

        $this->artisan('compromisos:romper-vencidos')->assertExitCode(0);

        $this->assertSame('roto', $this->estadoDe($vencido));
        $this->assertSame('pendiente', $this->estadoDe($vigente));

        // Sin periodo de gracia no significa romperlo el mismo día que vence: el
        // deudor tiene hasta el final de su fecha. Se rompe al día siguiente.
        $this->assertSame(
            'pendiente',
            $this->estadoDe($venceHoy),
            'Un compromiso que vence hoy todavía está en plazo.'
        );
    }

    public function test_la_fecha_de_resolucion_es_el_dia_siguiente_al_vencimiento_no_hoy(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $vencimiento = Carbon::today()->subDays(10);
        $id = $this->crearCompromiso($proyecto, $vencimiento);

        $this->artisan('compromisos:romper-vencidos')->assertExitCode(0);

        // Si la tarea no corre durante días, las promesas rotas tienen que
        // conservar la fecha en la que de verdad se rompieron, o los informes
        // por período se amontonan el día que alguien reactivó el cron.
        $this->assertSame(
            $vencimiento->copy()->addDay()->toDateString(),
            (string) DB::table('compromisos')->where('id', $id)->value('fecha_resolucion'),
        );
    }

    public function test_apaga_la_bandera_de_compromiso_vigente_del_caso(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $casoId = $this->crearCasoEn($proyecto);
        $this->crearCompromiso($proyecto, Carbon::today()->subDays(5), $casoId);

        DB::table('casos')->where('id', $casoId)->update(['tiene_compromiso_vigente' => true]);

        $this->artisan('compromisos:romper-vencidos')->assertExitCode(0);

        $this->assertSame(
            0,
            (int) DB::table('casos')->where('id', $casoId)->value('tiene_compromiso_vigente'),
            'La bandera es lo que lee la Vista de Trabajo para pintar «compromiso vigente».'
        );
    }

    public function test_avisa_al_dueno_para_que_llame(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);
        $id = $this->crearCompromiso($proyecto, Carbon::today()->subDay(), null, $gestor->id);

        $this->artisan('compromisos:romper-vencidos')->assertExitCode(0);

        $this->assertDatabaseHas('notificaciones', [
            'proyecto_id' => $proyecto->id,
            'destinatario_usuario_id' => $gestor->id,
            'tipo' => 'compromiso_roto',
            'entidad_id' => $id,
        ]);
    }

    public function test_es_idempotente(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $id = $this->crearCompromiso($proyecto, Carbon::today()->subDays(2));

        $this->artisan('compromisos:romper-vencidos')->assertExitCode(0);
        $resolucionPrimera = DB::table('compromisos')->where('id', $id)->value('fecha_resolucion');

        $this->artisan('compromisos:romper-vencidos')->assertExitCode(0);

        $this->assertSame($resolucionPrimera, DB::table('compromisos')->where('id', $id)->value('fecha_resolucion'));
        $this->assertSame(1, DB::table('notificaciones')->where('entidad_id', $id)->where('tipo', 'compromiso_roto')->count());
    }

    public function test_el_simulacro_no_cambia_nada(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $id = $this->crearCompromiso($proyecto, Carbon::today()->subDays(4));

        $this->artisan('compromisos:romper-vencidos --dry-run')->assertExitCode(0);

        $this->assertSame('pendiente', $this->estadoDe($id));
    }

    public function test_no_se_lleva_por_delante_los_compromisos_de_otro_mandante_cuando_se_limita_el_proyecto(): void
    {
        $a = $this->crearProyectoCobranza();
        $b = $this->crearProyectoCobranza();
        $deA = $this->crearCompromiso($a, Carbon::today()->subDays(2));
        $deB = $this->crearCompromiso($b, Carbon::today()->subDays(2));

        $this->artisan('compromisos:romper-vencidos', ['--proyecto' => $a->id])->assertExitCode(0);

        $this->assertSame('roto', $this->estadoDe($deA));
        $this->assertSame('pendiente', $this->estadoDe($deB));
    }

    public function test_sin_limitar_proyecto_alcanza_a_todos_los_mandantes(): void
    {
        $a = $this->crearProyectoCobranza();
        $b = $this->crearProyectoCobranza();
        $deA = $this->crearCompromiso($a, Carbon::today()->subDays(2));
        $deB = $this->crearCompromiso($b, Carbon::today()->subDays(2));

        // Es una tarea de plataforma: recorrer todos los proyectos es la
        // intención, y por eso el comando escribe `sinScopeProyecto()` en vez de
        // apoyarse en que fuera de HTTP el scope no filtra.
        $this->artisan('compromisos:romper-vencidos')->assertExitCode(0);

        $this->assertSame('roto', $this->estadoDe($deA));
        $this->assertSame('roto', $this->estadoDe($deB));
    }

    private function estadoDe(int $id): string
    {
        return (string) DB::table('compromisos')->where('id', $id)->value('estado');
    }

    private function crearCompromiso(stdClass $proyecto, Carbon $vencimiento, ?int $casoId = null, ?int $usuarioId = null): int
    {
        $casoId ??= $this->crearCasoEn($proyecto);
        $usuarioId ??= $this->crearGestor($proyecto)->id;

        $id = (int) DB::table('compromisos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'caso_id' => $casoId,
            'tipo_compromiso' => 'promesa_pago',
            'estado' => 'pendiente',
            'fecha_vencimiento' => $vencimiento->toDateString(),
            'usuario_id' => $usuarioId,
            'creada_en' => now(),
            'actualizada_en' => now(),
        ]);

        DB::table('compromisos_promesa_pago')->insert([
            'compromiso_id' => $id,
            'proyecto_id' => $proyecto->id,
            'monto' => '100.00',
            'moneda' => 'USD',
            'creada_en' => now(),
            'actualizada_en' => now(),
        ]);

        return $id;
    }
}
