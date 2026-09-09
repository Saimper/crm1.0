<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Cobranza;

use App\Modules\Cobranza\Application\DTOs\AvanzarDiasMoraInput;
use App\Modules\Cobranza\Application\DTOs\AvanzarDiasMoraOutput;
use App\Modules\Cobranza\Application\UseCases\AvanzarDiasMora;
use App\Modules\Cobranza\Domain\ValueObjects\DiasMora;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * `dias_mora` era la foto del archivo importado y nadie la movía: el cron de
 * tramos reclasificaba la cartera sobre un número muerto —4.729 cuentas con la
 * mora de hace 109 días—. Sin `fecha_vencimiento` no hay de dónde derivarla, así
 * que se envejece el número a partir del día al que corresponde (el ancla).
 */
final class AvanzarDiasMoraTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    /** Un mediodía UTC cualquiera: para un mandante sin zona, hoy es el 10. */
    private const AHORA = '2026-09-10 12:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow(Carbon::parse(self::AHORA, 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_una_cuenta_anclada_hace_tres_dias_suma_tres_y_queda_anclada_hoy(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cuenta = $this->crearCuenta($proyecto, diasMora: 10, ancla: '2026-09-07');

        $this->artisan('cobranza:avanzar-dias-mora')->assertSuccessful();

        $fila = $this->fila($cuenta);
        $this->assertSame(13, (int) $fila->dias_mora);
        $this->assertSame('2026-09-10', (string) $fila->dias_mora_actualizado_en, 'El ancla pasa a hoy.');
        $this->assertSame(
            '2026-09-07',
            (string) $fila->dias_mora_confirmado_en,
            'La confirmación es de las fuentes: el envejecimiento nunca la toca.'
        );
    }

    public function test_el_mismo_dia_es_no_op(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cuenta = $this->crearCuenta($proyecto, diasMora: 10, ancla: '2026-09-07');

        $primera = $this->ejecutar();
        $segunda = $this->ejecutar();

        $this->assertSame(1, $primera->avanzadosPorProyecto[(int) $proyecto->id]);
        $this->assertSame(0, $segunda->avanzadosPorProyecto[(int) $proyecto->id], 'Con el ancla en hoy no hay días que sumar.');
        $this->assertSame(13, (int) $this->fila($cuenta)->dias_mora);
    }

    public function test_una_cuenta_cerrada_no_envejece(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cuenta = $this->crearCuenta($proyecto, diasMora: 10, ancla: '2026-09-07');
        DB::table('casos')->where('id', $cuenta)->update(['cerrado_en' => '2026-09-08 10:00:00']);

        $this->artisan('cobranza:avanzar-dias-mora')->assertSuccessful();

        $this->assertSinCambios($cuenta, 10, '2026-09-07');
    }

    public function test_una_cuenta_eliminada_no_envejece(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cuenta = $this->crearCuenta($proyecto, diasMora: 10, ancla: '2026-09-07');
        DB::table('casos')->where('id', $cuenta)->update(['eliminada_en' => '2026-09-08 10:00:00']);

        $this->artisan('cobranza:avanzar-dias-mora')->assertSuccessful();

        $this->assertSinCambios($cuenta, 10, '2026-09-07');
    }

    public function test_una_cuenta_al_dia_sigue_al_dia(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cuenta = $this->crearCuenta($proyecto, diasMora: 0, ancla: '2026-09-01');

        $this->artisan('cobranza:avanzar-dias-mora')->assertSuccessful();

        // Sin fecha de vencimiento no se sabe cuándo entraría en mora: sólo el
        // siguiente archivo del cliente puede decir otra cosa.
        $this->assertSinCambios($cuenta, 0, '2026-09-01');
    }

    public function test_sin_ancla_no_se_avanza_y_se_cuenta_como_sin_fecha(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cuenta = $this->crearCuenta($proyecto, diasMora: 10, ancla: null);

        $salida = $this->ejecutar();

        $this->assertSame(1, $salida->sinFecha, 'No se sabe a qué día corresponde su valor.');
        $this->assertSame(0, $salida->avanzadosPorProyecto[(int) $proyecto->id]);
        $this->assertSame(10, (int) $this->fila($cuenta)->dias_mora);
        $this->assertNull($this->fila($cuenta)->dias_mora_actualizado_en);
    }

    public function test_no_se_pasa_del_techo_del_dominio_y_se_cuenta_en_tope(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $seSaldria = $this->crearCuenta($proyecto, diasMora: DiasMora::MAXIMO_RAZONABLE - 1, ancla: '2026-09-05');
        $cabeJusto = $this->crearCuenta($proyecto, diasMora: DiasMora::MAXIMO_RAZONABLE - 5, ancla: '2026-09-05');

        $salida = $this->ejecutar();

        // El VO rechazaría hidratar 14.604: mejor una mora vieja que una cuenta
        // que revienta la Vista de Trabajo.
        $this->assertSame(1, $salida->enTope);
        $this->assertSinCambios($seSaldria, DiasMora::MAXIMO_RAZONABLE - 1, '2026-09-05');

        $this->assertSame(DiasMora::MAXIMO_RAZONABLE, (int) $this->fila($cabeJusto)->dias_mora, 'El límite exacto pasa.');
        $this->assertSame('2026-09-10', (string) $this->fila($cabeJusto)->dias_mora_actualizado_en);
    }

    /**
     * Kiritimati (UTC+14) y Panamá (UTC−5): el mismo instante es un día
     * distinto para cada uno. Cada mandante envejece con su propio calendario,
     * y ninguno repite cuando al otro le toca.
     */
    public function test_cada_mandante_envejece_con_su_propio_dia(): void
    {
        $kiritimati = $this->crearMandante();
        $panama = $this->crearMandante();
        DB::table('mandantes')->where('id', $kiritimati->id)->update(['zona_horaria' => 'Pacific/Kiritimati']);
        DB::table('mandantes')->where('id', $panama->id)->update(['zona_horaria' => 'America/Panama']);

        $cuentaK = $this->crearCuenta($this->crearProyectoCobranza($kiritimati), diasMora: 10, ancla: '2026-09-09');
        $cuentaP = $this->crearCuenta($this->crearProyectoCobranza($panama), diasMora: 10, ancla: '2026-09-09');

        // 20:00 UTC del 9: en Kiritimati ya es el 10; en Panamá son las 15:00 del 9.
        Carbon::setTestNow(Carbon::parse('2026-09-09 20:00:00', 'UTC'));
        $this->artisan('cobranza:avanzar-dias-mora')->assertSuccessful();

        $this->assertSame(11, (int) $this->fila($cuentaK)->dias_mora, 'Para Kiritimati ya cambió el día.');
        $this->assertSame('2026-09-10', (string) $this->fila($cuentaK)->dias_mora_actualizado_en);
        $this->assertSinCambios($cuentaP, 10, '2026-09-09', 'Para Panamá sigue siendo el 9.');

        // 06:00 UTC del 10: en Panamá es la 01:00 del 10; en Kiritimati las
        // 20:00 del 10, el mismo día en el que ya se ancló.
        Carbon::setTestNow(Carbon::parse('2026-09-10 06:00:00', 'UTC'));
        $this->artisan('cobranza:avanzar-dias-mora')->assertSuccessful();

        $this->assertSame(11, (int) $this->fila($cuentaP)->dias_mora, 'Ahora le toca a Panamá.');
        $this->assertSame('2026-09-10', (string) $this->fila($cuentaP)->dias_mora_actualizado_en);
        $this->assertSame(11, (int) $this->fila($cuentaK)->dias_mora, 'Kiritimati no repite: su ancla ya era hoy.');
    }

    /** Aislamiento: acotar a un proyecto no toca la cartera de otro mandante. */
    public function test_acotar_por_proyecto_no_toca_otro_proyecto(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCobranza();
        $cuentaA = $this->crearCuenta($proyectoA, diasMora: 10, ancla: '2026-09-07');
        $cuentaB = $this->crearCuenta($proyectoB, diasMora: 10, ancla: '2026-09-07');

        $this->artisan('cobranza:avanzar-dias-mora', ['--proyecto' => $proyectoA->id])->assertSuccessful();

        $this->assertSame(13, (int) $this->fila($cuentaA)->dias_mora);
        $this->assertSinCambios($cuentaB, 10, '2026-09-07', 'El otro mandante ni se mira.');
    }

    public function test_sin_acotar_alcanza_a_todos_los_mandantes(): void
    {
        $cuentaA = $this->crearCuenta($this->crearProyectoCobranza(), diasMora: 10, ancla: '2026-09-07');
        $cuentaB = $this->crearCuenta($this->crearProyectoCobranza(), diasMora: 20, ancla: '2026-09-09');

        $this->artisan('cobranza:avanzar-dias-mora')->assertSuccessful();

        $this->assertSame(13, (int) $this->fila($cuentaA)->dias_mora);
        $this->assertSame(21, (int) $this->fila($cuentaB)->dias_mora);
    }

    public function test_el_simulacro_informa_pero_no_escribe(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cuenta = $this->crearCuenta($proyecto, diasMora: 10, ancla: '2026-09-07');

        $salida = $this->ejecutar(simulacro: true);
        $this->assertSame(1, $salida->avanzadosPorProyecto[(int) $proyecto->id], 'Dice lo que haría.');
        $this->assertSinCambios($cuenta, 10, '2026-09-07');

        $this->artisan('cobranza:avanzar-dias-mora --dry-run')
            ->expectsOutputToContain('1 cuentas avanzarían')
            ->assertSuccessful();
        $this->assertSinCambios($cuenta, 10, '2026-09-07');
    }

    /**
     * `actualizada_en` es ON UPDATE CURRENT_TIMESTAMP y la pisaba cualquier
     * UPDATE; el relleno histórico ya no pudo fiarse de ella. El avance diario
     * la deja quieta para no seguir borrando lo poco que significa.
     */
    public function test_avanzar_no_pisa_actualizada_en(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cuenta = $this->crearCuenta($proyecto, diasMora: 10, ancla: '2026-09-07', extra: [
            'actualizada_en' => '2026-08-01 10:00:00',
        ]);

        $this->artisan('cobranza:avanzar-dias-mora')->assertSuccessful();

        $this->assertSame(13, (int) $this->fila($cuenta)->dias_mora);
        $this->assertSame('2026-08-01 10:00:00', (string) $this->fila($cuenta)->actualizada_en);
    }

    public function test_avisa_de_las_cuentas_que_el_cliente_dejo_de_confirmar(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $hoy = Carbon::parse('2026-09-10');
        $hace46 = $hoy->copy()->subDays(DiasMora::DIAS_SIN_CONFIRMAR_AVISO + 1)->toDateString();
        $hace45 = $hoy->copy()->subDays(DiasMora::DIAS_SIN_CONFIRMAR_AVISO)->toDateString();

        $olvidada = $this->crearCuenta($proyecto, diasMora: 100, ancla: '2026-09-09', confirmada: $hace46);
        $this->crearCuenta($proyecto, diasMora: 100, ancla: '2026-09-09', confirmada: $hace45);
        $this->crearCuenta($proyecto, diasMora: 0, ancla: '2026-09-09', confirmada: $hace46);
        $cerrada = $this->crearCuenta($proyecto, diasMora: 100, ancla: '2026-09-09', confirmada: $hace46);
        DB::table('casos')->where('id', $cerrada)->update(['cerrado_en' => '2026-09-01 10:00:00']);

        $salida = $this->ejecutar();

        // Exactamente 45 días todavía no es «más de 45»; al día y cerradas no
        // son cuentas que el CRM esté envejeciendo a ciegas.
        $this->assertSame(1, $salida->sinConfirmarHaceTiempo);
        $this->assertSame(101, (int) $this->fila($olvidada)->dias_mora, 'Se avisa, pero se sigue envejeciendo: no hay foto que la cierre.');

        $this->artisan('cobranza:avanzar-dias-mora')
            ->expectsOutputToContain('más de '.DiasMora::DIAS_SIN_CONFIRMAR_AVISO.' días sin que el cliente confirme su mora')
            ->assertSuccessful();
    }

    public function test_no_avisa_cuando_todas_estan_confirmadas_hace_poco(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->crearCuenta($proyecto, diasMora: 100, ancla: '2026-09-09', confirmada: '2026-09-01');

        $this->artisan('cobranza:avanzar-dias-mora')
            ->doesntExpectOutputToContain('sin que el cliente confirme')
            ->assertSuccessful();
    }

    public function test_solo_envejece_proyectos_de_cobranza(): void
    {
        $cx = $this->crearProyectoCx();
        $cuenta = $this->crearCuenta($cx, diasMora: 10, ancla: '2026-09-07');

        $salida = $this->ejecutar(proyectoId: (int) $cx->id);

        $this->assertSame([], $salida->avanzadosPorProyecto, 'Un proyecto que no es de cobranza no entra ni aunque se pida.');
        $this->assertSinCambios($cuenta, 10, '2026-09-07');
    }

    private function ejecutar(?int $proyectoId = null, bool $simulacro = false): AvanzarDiasMoraOutput
    {
        return $this->app->make(AvanzarDiasMora::class)
            ->execute(new AvanzarDiasMoraInput(proyectoId: $proyectoId, simulacro: $simulacro));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function crearCuenta(stdClass $proyecto, ?int $diasMora, ?string $ancla, ?string $confirmada = null, array $extra = []): int
    {
        $casoId = $this->crearCasoEn($proyecto, ['tipo_caso' => 'cobranza']);

        DB::table('casos_cobranza')->insert(array_merge([
            'caso_id' => $casoId,
            'proyecto_id' => $proyecto->id,
            'numero_prestamo' => "P-{$casoId}",
            'dias_mora' => $diasMora,
            'dias_mora_actualizado_en' => $ancla,
            'dias_mora_confirmado_en' => $confirmada ?? $ancla,
        ], $extra));

        return $casoId;
    }

    private function fila(int $casoId): stdClass
    {
        /** @var stdClass $fila */
        $fila = DB::table('casos_cobranza')->where('caso_id', $casoId)->first();

        return $fila;
    }

    private function assertSinCambios(int $casoId, int $diasMora, ?string $ancla, string $motivo = ''): void
    {
        $fila = $this->fila($casoId);

        $this->assertSame($diasMora, (int) $fila->dias_mora, $motivo);
        $this->assertSame($ancla, $fila->dias_mora_actualizado_en === null ? null : (string) $fila->dias_mora_actualizado_en, $motivo);
    }

    /**
     * La tarea corre cada hora y 23 de esas 24 veces no tiene nada que hacer.
     * Si la busca arranca por `casos`, el coste es el tamaño de la cartera
     * —tabla temporal y filesort incluidos— aunque no haya una sola cuenta que
     * avanzar. Con el índice del ancla, no haber nada vencido es un rango
     * vacío. Se mira el plan de la consulta REAL, no de una parecida.
     */
    public function test_la_busqueda_de_lo_avanzable_va_por_el_indice_del_ancla(): void
    {
        self::assertTrue(
            Schema::hasIndex('casos_cobranza', AvanzarDiasMora::INDICE_AVANCE),
            'Sin el índice, cada pasada horaria recorre la cartera entera.',
        );

        $proyecto = $this->crearProyectoCobranza();
        foreach (range(1, 5) as $i) {
            $this->crearCuenta($proyecto, 10, '2026-09-01');
        }

        $consulta = app(AvanzarDiasMora::class)->avanzables((int) $proyecto->id, '2026-09-08');

        /** @var list<stdClass> $plan */
        $plan = DB::select('EXPLAIN '.$consulta->toSql(), $consulta->getBindings());

        self::assertNotEmpty($plan);
        $paso = $plan[0];

        self::assertSame('casos_cobranza', $paso->table, 'El plan tiene que arrancar por la tabla de cobranza. Plan: '.json_encode($plan));
        self::assertSame(AvanzarDiasMora::INDICE_AVANCE, $paso->key, 'Plan: '.json_encode($paso));
        self::assertStringNotContainsString('temporary', (string) ($paso->Extra ?? ''), 'Plan: '.json_encode($paso));
    }
}
