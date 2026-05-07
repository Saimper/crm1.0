<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Venta;

use App\Modules\Casos\Domain\Events\CasoCreado;
use App\Modules\Venta\Application\DTOs\RegistrarCasoLeadVentaInput;
use App\Modules\Venta\Application\UseCases\RegistrarCasoLeadVenta;
use App\Modules\Venta\Domain\Exceptions\CodigoLeadYaRegistrado;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RegistrarCasoLeadVentaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

    }

    public function test_registra_lead_crea_caso_base_y_especializacion(): void
    {
        [$proyectoId, $carteraId, $personaId, $estadoId] = $this->contexto();
        Event::fake([CasoCreado::class]);

        $output = $this->app->make(RegistrarCasoLeadVenta::class)->execute(new RegistrarCasoLeadVentaInput(
            proyectoId: $proyectoId,
            carteraId: $carteraId,
            personaId: $personaId,
            estadoCasoId: $estadoId,
            fechaIngreso: new DateTimeImmutable('2026-04-18'),
            prioridad: 100,
            codigoLead: 'LEAD-TEST-001',
            productoVentaId: $this->idProyecto('productos_venta', 'SEGURO_VIDA', $proyectoId),
            etapaEmbudoId: $this->idProyecto('etapas_embudo', 'CALIFICACION', $proyectoId),
            valorEstimadoMonto: '2500.00',
            moneda: 'USD',
            origenLead: 'Referido',
            fechaPrimerContacto: new DateTimeImmutable('2026-04-18'),
            fechaEstimadaCierre: new DateTimeImmutable('2026-05-18'),
        ));

        $this->assertDatabaseHas('casos', [
            'id' => $output->casoId,
            'proyecto_id' => $proyectoId,
            'tipo_caso' => 'lead_venta',
        ]);
        $this->assertDatabaseHas('casos_lead_venta', [
            'caso_id' => $output->casoId,
            'codigo_lead' => 'LEAD-TEST-001',
            'valor_estimado' => '2500.00',
            'moneda' => 'USD',
        ]);
        Event::assertDispatched(CasoCreado::class);
    }

    public function test_rechaza_codigo_lead_duplicado(): void
    {
        [$proyectoId, $carteraId, $personaId, $estadoId] = $this->contexto();
        $useCase = $this->app->make(RegistrarCasoLeadVenta::class);

        $useCase->execute($this->inputBase($proyectoId, $carteraId, $personaId, $estadoId, 'LEAD-DUP'));

        $this->expectException(CodigoLeadYaRegistrado::class);
        $useCase->execute($this->inputBase($proyectoId, $carteraId, $personaId, $estadoId, 'LEAD-DUP'));
    }

    /** @return array{int,int,int,int} */
    private function contexto(): array
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'VENTA_DEMO_2026')->value('id');
        $carteraId = (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->where('codigo', 'PREMIUM')->value('id');
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $estadoId = (int) DB::table('estados_caso')->where('proyecto_id', $proyectoId)->where('codigo', 'NUEVO')->value('id');

        $personaId = (int) DB::table('personas')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'tipo_persona' => 'fisica',
            'tipo_identificacion_id' => $tipoCed,
            'identificacion' => (string) random_int(1_000_000_000, 9_999_999_999),
            'nombres' => 'Tester',
            'apellidos' => 'Venta',
        ]);

        return [$proyectoId, $carteraId, $personaId, $estadoId];
    }

    private function inputBase(int $proyectoId, int $carteraId, int $personaId, int $estadoId, string $codigo): RegistrarCasoLeadVentaInput
    {
        return new RegistrarCasoLeadVentaInput(
            proyectoId: $proyectoId,
            carteraId: $carteraId,
            personaId: $personaId,
            estadoCasoId: $estadoId,
            fechaIngreso: new DateTimeImmutable('2026-04-18'),
            prioridad: 100,
            codigoLead: $codigo,
            productoVentaId: null,
            etapaEmbudoId: null,
            valorEstimadoMonto: '100.00',
            moneda: 'USD',
            origenLead: null,
            fechaPrimerContacto: new DateTimeImmutable('2026-04-18'),
            fechaEstimadaCierre: null,
        );
    }

    private function idProyecto(string $tabla, string $codigo, int $proyectoId): int
    {
        return (int) DB::table($tabla)->where('proyecto_id', $proyectoId)->where('codigo', $codigo)->value('id');
    }
}
