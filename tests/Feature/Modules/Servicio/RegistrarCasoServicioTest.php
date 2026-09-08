<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Servicio;

use App\Modules\Casos\Domain\Events\CasoCreado;
use App\Modules\Servicio\Application\DTOs\RegistrarCasoServicioInput;
use App\Modules\Servicio\Application\UseCases\RegistrarCasoServicio;
use App\Modules\Servicio\Domain\Exceptions\CodigoServicioYaRegistrado;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class RegistrarCasoServicioTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_registra_caso_servicio_crea_caso_base_y_especializacion(): void
    {
        [$proyecto, $carteraId, $personaId, $estadoId] = $this->contexto();
        Event::fake([CasoCreado::class]);

        $output = $this->app->make(RegistrarCasoServicio::class)->execute(new RegistrarCasoServicioInput(
            proyectoId: (int) $proyecto->id,
            carteraId: $carteraId,
            personaId: $personaId,
            estadoCasoId: $estadoId,
            fechaIngreso: new DateTimeImmutable('2026-04-20'),
            prioridad: 100,
            codigoServicio: 'SVC-TEST-001',
            tipoAccionServicioId: $this->crearTipoAccionServicioEn($proyecto, 'INSTALACION'),
            estadoTecnicoId: $this->crearEstadoTecnicoEn($proyecto, 'AGENDADO'),
            direccionServicio: 'Dirección de prueba',
            tecnicoAsignado: 'Test Técnico',
            fechaSolicitud: new DateTimeImmutable('2026-04-20'),
            fechaProgramada: new DateTimeImmutable('2026-04-25 10:00:00'),
        ));

        $this->assertDatabaseHas('casos', [
            'id' => $output->casoId,
            'proyecto_id' => $proyecto->id,
            'tipo_caso' => 'servicio',
        ]);
        $this->assertDatabaseHas('casos_servicio', [
            'caso_id' => $output->casoId,
            'codigo_servicio' => 'SVC-TEST-001',
            'tecnico_asignado' => 'Test Técnico',
        ]);
        Event::assertDispatched(CasoCreado::class);
    }

    public function test_rechaza_codigo_servicio_duplicado(): void
    {
        [$proyecto, $carteraId, $personaId, $estadoId] = $this->contexto();
        $useCase = $this->app->make(RegistrarCasoServicio::class);

        $useCase->execute($this->inputBase((int) $proyecto->id, $carteraId, $personaId, $estadoId, 'SVC-DUP'));

        $this->expectException(CodigoServicioYaRegistrado::class);
        $useCase->execute($this->inputBase((int) $proyecto->id, $carteraId, $personaId, $estadoId, 'SVC-DUP'));
    }

    /** @return array{stdClass,int,int,int} */
    private function contexto(): array
    {
        $proyecto = $this->crearProyectoServicio();
        $cartera = $this->crearCarteraEn($proyecto, 'RESIDENCIAL');
        $estado = $this->crearEstadoCasoEn($proyecto, 'PENDIENTE');
        $persona = $this->crearPersonaEn($proyecto);

        return [$proyecto, (int) $cartera->id, (int) $persona->id, (int) $estado->id];
    }

    private function inputBase(int $proyectoId, int $carteraId, int $personaId, int $estadoId, string $codigo): RegistrarCasoServicioInput
    {
        return new RegistrarCasoServicioInput(
            proyectoId: $proyectoId,
            carteraId: $carteraId,
            personaId: $personaId,
            estadoCasoId: $estadoId,
            fechaIngreso: new DateTimeImmutable('2026-04-20'),
            prioridad: 100,
            codigoServicio: $codigo,
            tipoAccionServicioId: null,
            estadoTecnicoId: null,
            direccionServicio: null,
            tecnicoAsignado: null,
            fechaSolicitud: new DateTimeImmutable('2026-04-20'),
            fechaProgramada: null,
        );
    }

    /** Catálogo por proyecto (§8) que el trait compartido no cubre todavía. */
    private function crearTipoAccionServicioEn(stdClass $proyecto, string $codigo): int
    {
        return (int) DB::table('tipos_accion_servicio')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => $codigo,
            'nombre' => 'Tipo acción '.$codigo,
            'activo' => true,
            'orden' => 10,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
    }

    private function crearEstadoTecnicoEn(stdClass $proyecto, string $codigo): int
    {
        return (int) DB::table('estados_tecnicos')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => $codigo,
            'nombre' => 'Estado técnico '.$codigo,
            'activo' => true,
            'orden' => 10,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
    }
}
