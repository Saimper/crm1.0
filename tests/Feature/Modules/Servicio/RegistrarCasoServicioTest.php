<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Servicio;

use App\Modules\Casos\Domain\Events\CasoCreado;
use App\Modules\Servicio\Application\DTOs\RegistrarCasoServicioInput;
use App\Modules\Servicio\Application\UseCases\RegistrarCasoServicio;
use App\Modules\Servicio\Domain\Exceptions\CodigoServicioYaRegistrado;
use Database\Seeders\Catalogos\TiposIdentificacionSeeder;
use Database\Seeders\Servicio\EstadosCasoServicioDemoSeeder;
use Database\Seeders\Servicio\EstadosTecnicosDemoSeeder;
use Database\Seeders\Servicio\TiposAccionServicioDemoSeeder;
use Database\Seeders\Tenancy\CarterasDemoSeeder;
use Database\Seeders\Tenancy\MandantesDemoSeeder;
use Database\Seeders\Tenancy\ProyectosDemoSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RegistrarCasoServicioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            MandantesDemoSeeder::class,
            ProyectosDemoSeeder::class,
            CarterasDemoSeeder::class,
            TiposIdentificacionSeeder::class,
            EstadosCasoServicioDemoSeeder::class,
            TiposAccionServicioDemoSeeder::class,
            EstadosTecnicosDemoSeeder::class,
        ]);
    }

    public function test_registra_caso_servicio_crea_caso_base_y_especializacion(): void
    {
        [$proyectoId, $carteraId, $personaId, $estadoId] = $this->contexto();
        Event::fake([CasoCreado::class]);

        $output = $this->app->make(RegistrarCasoServicio::class)->execute(new RegistrarCasoServicioInput(
            proyectoId:           $proyectoId,
            carteraId:            $carteraId,
            personaId:            $personaId,
            estadoCasoId:         $estadoId,
            fechaIngreso:         new DateTimeImmutable('2026-04-20'),
            prioridad:            100,
            codigoServicio:       'SVC-TEST-001',
            tipoAccionServicioId: $this->idProyecto('tipos_accion_servicio', 'INSTALACION', $proyectoId),
            estadoTecnicoId:      $this->idProyecto('estados_tecnicos', 'AGENDADO', $proyectoId),
            direccionServicio:    'Dirección de prueba',
            tecnicoAsignado:      'Test Técnico',
            fechaSolicitud:       new DateTimeImmutable('2026-04-20'),
            fechaProgramada:      new DateTimeImmutable('2026-04-25 10:00:00'),
        ));

        $this->assertDatabaseHas('casos', [
            'id'          => $output->casoId,
            'proyecto_id' => $proyectoId,
            'tipo_caso'   => 'servicio',
        ]);
        $this->assertDatabaseHas('casos_servicio', [
            'caso_id'          => $output->casoId,
            'codigo_servicio'  => 'SVC-TEST-001',
            'tecnico_asignado' => 'Test Técnico',
        ]);
        Event::assertDispatched(CasoCreado::class);
    }

    public function test_rechaza_codigo_servicio_duplicado(): void
    {
        [$proyectoId, $carteraId, $personaId, $estadoId] = $this->contexto();
        $useCase = $this->app->make(RegistrarCasoServicio::class);

        $useCase->execute($this->inputBase($proyectoId, $carteraId, $personaId, $estadoId, 'SVC-DUP'));

        $this->expectException(CodigoServicioYaRegistrado::class);
        $useCase->execute($this->inputBase($proyectoId, $carteraId, $personaId, $estadoId, 'SVC-DUP'));
    }

    /** @return array{int,int,int,int} */
    private function contexto(): array
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'SERVICIO_DEMO_2026')->value('id');
        $carteraId  = (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->where('codigo', 'RESIDENCIAL')->value('id');
        $tipoCed    = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $estadoId   = (int) DB::table('estados_caso')->where('proyecto_id', $proyectoId)->where('codigo', 'PENDIENTE')->value('id');

        $personaId = (int) DB::table('personas')->insertGetId([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoId,
            'tipo_persona' => 'fisica', 'tipo_identificacion_id' => $tipoCed,
            'identificacion' => (string) random_int(1_000_000_000, 9_999_999_999),
            'nombres' => 'Tester', 'apellidos' => 'Servicio',
        ]);

        return [$proyectoId, $carteraId, $personaId, $estadoId];
    }

    private function inputBase(int $proyectoId, int $carteraId, int $personaId, int $estadoId, string $codigo): RegistrarCasoServicioInput
    {
        return new RegistrarCasoServicioInput(
            proyectoId:           $proyectoId,
            carteraId:            $carteraId,
            personaId:            $personaId,
            estadoCasoId:         $estadoId,
            fechaIngreso:         new DateTimeImmutable('2026-04-20'),
            prioridad:            100,
            codigoServicio:       $codigo,
            tipoAccionServicioId: null,
            estadoTecnicoId:      null,
            direccionServicio:    null,
            tecnicoAsignado:      null,
            fechaSolicitud:       new DateTimeImmutable('2026-04-20'),
            fechaProgramada:      null,
        );
    }

    private function idProyecto(string $tabla, string $codigo, int $proyectoId): int
    {
        return (int) DB::table($tabla)->where('proyecto_id', $proyectoId)->where('codigo', $codigo)->value('id');
    }
}
