<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Venta;

use App\Modules\Casos\Domain\Events\CasoCreado;
use App\Modules\Venta\Application\DTOs\RegistrarCasoLeadVentaInput;
use App\Modules\Venta\Application\UseCases\RegistrarCasoLeadVenta;
use App\Modules\Venta\Domain\Exceptions\CodigoLeadYaRegistrado;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class RegistrarCasoLeadVentaTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_registra_lead_crea_caso_base_y_especializacion(): void
    {
        [$proyecto, $carteraId, $personaId, $estadoId] = $this->contexto();
        Event::fake([CasoCreado::class]);

        $output = $this->app->make(RegistrarCasoLeadVenta::class)->execute(new RegistrarCasoLeadVentaInput(
            proyectoId: (int) $proyecto->id,
            carteraId: $carteraId,
            personaId: $personaId,
            estadoCasoId: $estadoId,
            fechaIngreso: new DateTimeImmutable('2026-04-18'),
            prioridad: 100,
            codigoLead: 'LEAD-TEST-001',
            productoVentaId: $this->crearProductoVentaEn($proyecto, 'SEGURO_VIDA'),
            etapaEmbudoId: $this->crearEtapaEmbudoEn($proyecto, 'CALIFICACION'),
            valorEstimadoMonto: '2500.00',
            moneda: 'USD',
            origenLead: 'Referido',
            fechaPrimerContacto: new DateTimeImmutable('2026-04-18'),
            fechaEstimadaCierre: new DateTimeImmutable('2026-05-18'),
        ));

        $this->assertDatabaseHas('casos', [
            'id' => $output->casoId,
            'proyecto_id' => $proyecto->id,
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
        [$proyecto, $carteraId, $personaId, $estadoId] = $this->contexto();
        $useCase = $this->app->make(RegistrarCasoLeadVenta::class);

        $useCase->execute($this->inputBase((int) $proyecto->id, $carteraId, $personaId, $estadoId, 'LEAD-DUP'));

        $this->expectException(CodigoLeadYaRegistrado::class);
        $useCase->execute($this->inputBase((int) $proyecto->id, $carteraId, $personaId, $estadoId, 'LEAD-DUP'));
    }

    /** @return array{stdClass,int,int,int} */
    private function contexto(): array
    {
        $proyecto = $this->crearProyectoVenta();
        $cartera = $this->crearCarteraEn($proyecto, 'PREMIUM');
        $persona = $this->crearPersonaEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto, 'NUEVO');

        return [$proyecto, (int) $cartera->id, (int) $persona->id, (int) $estado->id];
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

    /** Catálogo por proyecto de §8; el trait no lo cubre, así que se inserta aquí. */
    private function crearProductoVentaEn(stdClass $proyecto, string $codigo): int
    {
        return (int) DB::table('productos_venta')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => $codigo,
            'nombre' => 'Producto '.$codigo,
            'activo' => true,
            'orden' => 10,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
    }

    private function crearEtapaEmbudoEn(stdClass $proyecto, string $codigo, int $nivel = 1): int
    {
        return (int) DB::table('etapas_embudo')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => $codigo,
            'nombre' => 'Etapa '.$codigo,
            'nivel' => $nivel,
            'probabilidad_cierre' => 25,
            'activo' => true,
            'orden' => 10,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
    }
}
