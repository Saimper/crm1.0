<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Casos;

use App\Modules\Casos\Application\DTOs\CerrarCasoInput;
use App\Modules\Casos\Application\UseCases\CerrarCaso;
use App\Modules\Casos\Domain\Exceptions\TransicionCasoInvalida;
use App\Modules\Casos\Domain\Events\CasoCerrado;
use Database\Seeders\Casos\EstadosCasoDemoSeeder;
use Database\Seeders\Catalogos\TiposIdentificacionSeeder;
use Database\Seeders\Tenancy\CarterasDemoSeeder;
use Database\Seeders\Tenancy\MandantesDemoSeeder;
use Database\Seeders\Tenancy\ProyectosDemoSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CerrarCasoTest extends TestCase
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
            EstadosCasoDemoSeeder::class,
        ]);
    }

    public function test_cerrar_caso_cambia_estado_y_dispara_evento(): void
    {
        $casoId = $this->crearCasoAbierto();
        $estadoPagadoId = (int) DB::table('estados_caso')->where('codigo', 'PAGADO')->value('id');
        Event::fake([CasoCerrado::class]);

        $this->app->make(CerrarCaso::class)->execute(new CerrarCasoInput(
            casoId:               $casoId,
            estadoCasoTerminalId: $estadoPagadoId,
            cerradoEn:            new DateTimeImmutable('2026-05-01 10:00:00'),
        ));

        $this->assertDatabaseHas('casos', [
            'id'             => $casoId,
            'estado_caso_id' => $estadoPagadoId,
        ]);
        $this->assertNotNull(DB::table('casos')->where('id', $casoId)->value('cerrado_en'));

        Event::assertDispatched(CasoCerrado::class);
    }

    public function test_no_permite_cerrar_un_caso_ya_cerrado(): void
    {
        $casoId = $this->crearCasoAbierto();
        $estadoPagadoId = (int) DB::table('estados_caso')->where('codigo', 'PAGADO')->value('id');
        $useCase = $this->app->make(CerrarCaso::class);

        $useCase->execute(new CerrarCasoInput($casoId, $estadoPagadoId, new DateTimeImmutable('2026-05-01')));

        $this->expectException(TransicionCasoInvalida::class);
        $useCase->execute(new CerrarCasoInput($casoId, $estadoPagadoId, new DateTimeImmutable('2026-05-02')));
    }

    private function crearCasoAbierto(): int
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $carteraId  = (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->where('codigo', 'CONSUMO')->value('id');
        $tipoCed    = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $estadoAbiertoId = (int) DB::table('estados_caso')->where('codigo', 'ABIERTO')->value('id');

        $personaId = (int) DB::table('personas')->insertGetId([
            'public_id'              => (string) Str::ulid(),
            'proyecto_id'            => $proyectoId,
            'tipo_persona'           => 'fisica',
            'tipo_identificacion_id' => $tipoCed,
            'identificacion'         => (string) random_int(1_000_000_000, 9_999_999_999),
            'nombres'                => 'Test',
            'apellidos'              => 'User',
        ]);

        return (int) DB::table('casos')->insertGetId([
            'public_id'      => (string) Str::ulid(),
            'proyecto_id'    => $proyectoId,
            'cartera_id'     => $carteraId,
            'persona_id'     => $personaId,
            'tipo_caso'      => 'cobranza',
            'estado_caso_id' => $estadoAbiertoId,
            'fecha_ingreso'  => '2026-04-17',
            'prioridad'      => 100,
        ]);
    }
}
