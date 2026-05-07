<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Cobranza;

use App\Modules\Casos\Domain\Events\CasoCreado;
use App\Modules\Cobranza\Application\DTOs\RegistrarCasoCobranzaInput;
use App\Modules\Cobranza\Application\UseCases\RegistrarCasoCobranza;
use App\Modules\Cobranza\Domain\Exceptions\NumeroPrestamoYaRegistrado;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RegistrarCasoCobranzaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

    }

    public function test_registra_caso_cobranza_crea_caso_base_y_especializacion(): void
    {
        [$proyectoId, $carteraId, $personaId, $estadoAbiertoId] = $this->setupBase();
        Event::fake([CasoCreado::class]);

        $output = $this->app->make(RegistrarCasoCobranza::class)->execute(new RegistrarCasoCobranzaInput(
            proyectoId: $proyectoId,
            carteraId: $carteraId,
            personaId: $personaId,
            estadoCasoId: $estadoAbiertoId,
            fechaIngreso: new DateTimeImmutable('2026-04-17'),
            prioridad: 100,
            numeroPrestamo: 'PRST-0001',
            moneda: 'USD',
            montoOriginal: '10000.00',
            saldoCapital: '8000.00',
            saldoInteres: '200.00',
            saldoTotal: '8200.00',
            cuotaMensual: '850.00',
            cuotasTotales: 12,
            cuotasPagadas: 2,
            diasMora: 30,
            fechaDesembolso: new DateTimeImmutable('2026-01-01'),
            fechaVencimiento: new DateTimeImmutable('2027-01-01'),
        ));

        $this->assertIsInt($output->casoId);
        $this->assertDatabaseHas('casos', [
            'id' => $output->casoId,
            'proyecto_id' => $proyectoId,
            'tipo_caso' => 'cobranza',
        ]);
        $this->assertDatabaseHas('casos_cobranza', [
            'caso_id' => $output->casoId,
            'proyecto_id' => $proyectoId,
            'numero_prestamo' => 'PRST-0001',
            'moneda' => 'USD',
            'cuotas_totales' => 12,
        ]);
        Event::assertDispatched(CasoCreado::class);
    }

    public function test_rechaza_numero_prestamo_duplicado_en_mismo_proyecto(): void
    {
        [$proyectoId, $carteraId, $personaId, $estadoAbiertoId] = $this->setupBase();
        $useCase = $this->app->make(RegistrarCasoCobranza::class);

        $useCase->execute($this->inputBase($proyectoId, $carteraId, $personaId, $estadoAbiertoId, 'PRST-0002'));

        $this->expectException(NumeroPrestamoYaRegistrado::class);
        $useCase->execute($this->inputBase($proyectoId, $carteraId, $personaId, $estadoAbiertoId, 'PRST-0002'));
    }

    public function test_permite_mismo_numero_prestamo_en_proyectos_distintos(): void
    {
        [$proyectoA, $carteraA, $personaA, $estadoA] = $this->setupBase();

        $mandanteId = (int) DB::table('mandantes')->where('codigo', 'BPO_DEMO')->value('id');
        $proyectoB = (int) DB::table('proyectos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'mandante_id' => $mandanteId,
            'codigo' => 'COBRANZA_PROYB_2026',
            'nombre' => 'Cobranza Proyecto B',
            'tipo_operacion' => 'cobranza',
            'activo' => true,
            'fecha_inicio' => '2026-04-17',
        ]);
        $carteraB = (int) DB::table('carteras')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoB,
            'codigo' => 'CONSUMO',
            'nombre' => 'Cartera Consumo B',
            'activo' => true,
        ]);
        $estadoB = (int) DB::table('estados_caso')->insertGetId([
            'proyecto_id' => $proyectoB,
            'codigo' => 'ABIERTO',
            'nombre' => 'Abierto',
            'activo' => true,
            'orden' => 10,
        ]);
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $personaB = (int) DB::table('personas')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoB,
            'tipo_persona' => 'fisica',
            'tipo_identificacion_id' => $tipoCed,
            'identificacion' => (string) random_int(1_000_000_000, 9_999_999_999),
            'nombres' => 'Persona',
            'apellidos' => 'Proyecto B',
        ]);

        $useCase = $this->app->make(RegistrarCasoCobranza::class);
        $outA = $useCase->execute($this->inputBase($proyectoA, $carteraA, $personaA, $estadoA, 'PRST-SHARED'));
        $outB = $useCase->execute($this->inputBase($proyectoB, $carteraB, $personaB, $estadoB, 'PRST-SHARED'));

        $this->assertNotSame($outA->casoId, $outB->casoId);
        $this->assertSame(2, DB::table('casos_cobranza')->where('numero_prestamo', 'PRST-SHARED')->count());
    }

    /**
     * @return array{int,int,int,int}
     */
    private function setupBase(): array
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $carteraId = (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->where('codigo', 'CONSUMO')->value('id');
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $estadoAbiertoId = (int) DB::table('estados_caso')->where('proyecto_id', $proyectoId)->where('codigo', 'ABIERTO')->value('id');

        $personaId = (int) DB::table('personas')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'tipo_persona' => 'fisica',
            'tipo_identificacion_id' => $tipoCed,
            'identificacion' => (string) random_int(1_000_000_000, 9_999_999_999),
            'nombres' => 'Juan',
            'apellidos' => 'Tester',
        ]);

        return [$proyectoId, $carteraId, $personaId, $estadoAbiertoId];
    }

    private function inputBase(int $proyectoId, int $carteraId, int $personaId, int $estadoId, string $numero): RegistrarCasoCobranzaInput
    {
        return new RegistrarCasoCobranzaInput(
            proyectoId: $proyectoId,
            carteraId: $carteraId,
            personaId: $personaId,
            estadoCasoId: $estadoId,
            fechaIngreso: new DateTimeImmutable('2026-04-17'),
            prioridad: 100,
            numeroPrestamo: $numero,
            moneda: 'USD',
            montoOriginal: '10000.00',
            saldoCapital: '8000.00',
            saldoInteres: '200.00',
            saldoTotal: '8200.00',
            cuotaMensual: '850.00',
            cuotasTotales: 12,
            cuotasPagadas: 2,
            diasMora: 30,
            fechaDesembolso: new DateTimeImmutable('2026-01-01'),
            fechaVencimiento: new DateTimeImmutable('2027-01-01'),
        );
    }
}
