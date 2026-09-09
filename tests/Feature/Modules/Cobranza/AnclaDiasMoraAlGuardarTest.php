<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Cobranza;

use App\Modules\Cobranza\Application\DTOs\RegistrarCasoCobranzaInput;
use App\Modules\Cobranza\Application\UseCases\RegistrarCasoCobranza;
use App\Modules\Cobranza\Domain\Contracts\CasoCobranzaRepository;
use App\Modules\Cobranza\Domain\Entities\CasoCobranza;
use App\Modules\Cobranza\Domain\ValueObjects\DiasMora;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Quien afirma una mora la ancla al día en que la afirma. El repositorio es
 * una fuente (alta a mano, edición por dominio): mueve las dos fechas, y sólo
 * cuando el valor cambia.
 */
final class AnclaDiasMoraAlGuardarTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_al_registrar_una_cuenta_con_mora_las_dos_fechas_son_hoy_del_cliente(): void
    {
        $mandante = $this->crearMandante();
        DB::table('mandantes')->where('id', $mandante->id)->update(['zona_horaria' => 'Pacific/Kiritimati']);
        $proyecto = $this->crearProyectoCobranza($mandante);

        // Mediodía UTC del 9: en Kiritimati (UTC+14) ya son las 02:00 del 10.
        Carbon::setTestNow(Carbon::parse('2026-09-09 12:00:00', 'UTC'));

        $casoId = $this->registrar($proyecto, diasMora: 30);

        $fila = $this->fila($casoId);
        $this->assertSame(30, (int) $fila->dias_mora);
        $this->assertSame('2026-09-10', (string) $fila->dias_mora_actualizado_en, 'El día del cliente, no el del servidor.');
        $this->assertSame('2026-09-10', (string) $fila->dias_mora_confirmado_en);
    }

    public function test_una_cuenta_registrada_sin_mora_no_recibe_fechas(): void
    {
        $casoId = $this->registrar($this->crearProyectoCobranza(), diasMora: null);

        $fila = $this->fila($casoId);
        $this->assertNull($fila->dias_mora);
        $this->assertNull($fila->dias_mora_actualizado_en, 'No hay valor al que ponerle fecha.');
        $this->assertNull($fila->dias_mora_confirmado_en);
    }

    public function test_guardar_sin_cambiar_la_mora_no_mueve_las_fechas(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $casoId = $this->registrar($proyecto, diasMora: 30);
        DB::table('casos_cobranza')->where('caso_id', $casoId)->update([
            'dias_mora_actualizado_en' => '2026-01-05',
            'dias_mora_confirmado_en' => '2026-01-01',
        ]);

        $repo = $this->app->make(CasoCobranzaRepository::class);
        $cuenta = $repo->buscarPorCasoId($casoId);
        $this->assertNotNull($cuenta);
        $repo->save($cuenta);

        // Volver a guardar la misma mora no es afirmarla de nuevo: mover el
        // ancla a hoy sin sumar los días perdería lo que va del 5 de enero a hoy.
        $fila = $this->fila($casoId);
        $this->assertSame('2026-01-05', (string) $fila->dias_mora_actualizado_en);
        $this->assertSame('2026-01-01', (string) $fila->dias_mora_confirmado_en);
    }

    public function test_cambiar_la_mora_reancla_las_dos_fechas(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $casoId = $this->registrar($proyecto, diasMora: 30);
        DB::table('casos_cobranza')->where('caso_id', $casoId)->update([
            'dias_mora_actualizado_en' => '2026-01-05',
            'dias_mora_confirmado_en' => '2026-01-01',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-09-10 12:00:00', 'UTC'));

        $repo = $this->app->make(CasoCobranzaRepository::class);
        $cuenta = $repo->buscarPorCasoId($casoId);
        $this->assertNotNull($cuenta);
        $repo->save($this->conMora($cuenta, 45));

        $fila = $this->fila($casoId);
        $this->assertSame(45, (int) $fila->dias_mora);
        $this->assertSame('2026-09-10', (string) $fila->dias_mora_actualizado_en);
        $this->assertSame('2026-09-10', (string) $fila->dias_mora_confirmado_en);
    }

    private function registrar(stdClass $proyecto, ?int $diasMora): int
    {
        $cartera = $this->crearCarteraEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);

        return $this->app->make(RegistrarCasoCobranza::class)->execute(new RegistrarCasoCobranzaInput(
            proyectoId: (int) $proyecto->id,
            carteraId: (int) $cartera->id,
            personaId: (int) $persona->id,
            estadoCasoId: (int) $estado->id,
            fechaIngreso: new DateTimeImmutable('2026-09-01'),
            prioridad: 100,
            numeroPrestamo: 'PRST-'.$persona->id,
            diasMora: $diasMora,
        ))->casoId;
    }

    private function conMora(CasoCobranza $cuenta, int $dias): CasoCobranza
    {
        return CasoCobranza::reconstituir(
            casoId: $cuenta->casoId,
            proyectoId: $cuenta->proyectoId,
            numeroPrestamo: $cuenta->numeroPrestamo,
            montoOriginal: $cuenta->montoOriginal,
            saldoCapital: $cuenta->saldoCapital,
            saldoInteres: $cuenta->saldoInteres,
            saldoTotal: $cuenta->saldoTotal,
            cuotaMensual: $cuenta->cuotaMensual,
            cuotasTotales: $cuenta->cuotasTotales,
            cuotasPagadas: $cuenta->cuotasPagadas,
            diasMora: new DiasMora($dias),
            tramoMoraId: $cuenta->tramoMoraId,
            fechaDesembolso: $cuenta->fechaDesembolso,
            fechaVencimiento: $cuenta->fechaVencimiento,
        );
    }

    private function fila(int $casoId): stdClass
    {
        /** @var stdClass $fila */
        $fila = DB::table('casos_cobranza')->where('caso_id', $casoId)->first();

        return $fila;
    }
}
