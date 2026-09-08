<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Cobranza;

use App\Modules\Casos\Domain\Events\CasoCreado;
use App\Modules\Cobranza\Application\DTOs\RegistrarCasoCobranzaInput;
use App\Modules\Cobranza\Application\UseCases\RegistrarCasoCobranza;
use App\Modules\Cobranza\Domain\Exceptions\NumeroPrestamoYaRegistrado;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class RegistrarCasoCobranzaTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
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
        $mandante = $this->crearMandante();

        [$proyectoA, $carteraA, $personaA, $estadoA] = $this->setupBase($mandante);
        [$proyectoB, $carteraB, $personaB, $estadoB] = $this->setupBase($mandante);

        $this->assertNotSame($proyectoA, $proyectoB);

        $useCase = $this->app->make(RegistrarCasoCobranza::class);
        $outA = $useCase->execute($this->inputBase($proyectoA, $carteraA, $personaA, $estadoA, 'PRST-SHARED'));
        $outB = $useCase->execute($this->inputBase($proyectoB, $carteraB, $personaB, $estadoB, 'PRST-SHARED'));

        $this->assertNotSame($outA->casoId, $outB->casoId);
        $this->assertSame(2, DB::table('casos_cobranza')->where('numero_prestamo', 'PRST-SHARED')->count());
    }

    /**
     * Proyecto de cobranza con cartera, estado abierto y persona: lo mínimo que
     * pide el UseCase. Antes venía de los *DemoSeeder borrados.
     *
     * @return array{int,int,int,int}
     */
    private function setupBase(?stdClass $mandante = null): array
    {
        $proyecto = $this->crearProyectoCobranza($mandante);
        $cartera = $this->crearCarteraEn($proyecto, 'CONSUMO');
        $estadoAbierto = $this->crearEstadoCasoEn($proyecto, 'ABIERTO');
        $persona = $this->crearPersonaEn($proyecto);

        return [
            (int) $proyecto->id,
            (int) $cartera->id,
            (int) $persona->id,
            (int) $estadoAbierto->id,
        ];
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
