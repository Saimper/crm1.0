<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Casos;

use App\Modules\Casos\Application\DTOs\CerrarCasoInput;
use App\Modules\Casos\Application\UseCases\CerrarCaso;
use App\Modules\Casos\Domain\Events\CasoCerrado;
use App\Modules\Casos\Domain\Exceptions\TransicionCasoInvalida;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class CerrarCasoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    private stdClass $proyecto;

    private int $estadoAbiertoId;

    private int $estadoPagadoId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->proyecto = $this->crearProyectoCobranza();
        $this->estadoAbiertoId = (int) $this->crearEstadoCasoEn($this->proyecto, 'ABIERTO')->id;
        $this->estadoPagadoId = (int) $this->crearEstadoCasoEn($this->proyecto, 'PAGADO', esTerminal: true)->id;
    }

    public function test_cerrar_caso_cambia_estado_y_dispara_evento(): void
    {
        $casoId = $this->crearCasoAbierto();
        Event::fake([CasoCerrado::class]);

        $this->app->make(CerrarCaso::class)->execute(new CerrarCasoInput(
            casoId: $casoId,
            estadoCasoTerminalId: $this->estadoPagadoId,
            cerradoEn: new DateTimeImmutable('2026-05-01 10:00:00'),
        ));

        $this->assertDatabaseHas('casos', [
            'id' => $casoId,
            'estado_caso_id' => $this->estadoPagadoId,
        ]);
        $this->assertNotNull(DB::table('casos')->where('id', $casoId)->value('cerrado_en'));

        Event::assertDispatched(CasoCerrado::class);
    }

    public function test_no_permite_cerrar_un_caso_ya_cerrado(): void
    {
        $casoId = $this->crearCasoAbierto();
        $useCase = $this->app->make(CerrarCaso::class);

        $useCase->execute(new CerrarCasoInput($casoId, $this->estadoPagadoId, new DateTimeImmutable('2026-05-01')));

        $this->expectException(TransicionCasoInvalida::class);
        $useCase->execute(new CerrarCasoInput($casoId, $this->estadoPagadoId, new DateTimeImmutable('2026-05-02')));
    }

    private function crearCasoAbierto(): int
    {
        return $this->crearCasoEn($this->proyecto, [
            'estado' => DB::table('estados_caso')->find($this->estadoAbiertoId),
            'tipo_caso' => 'cobranza',
            'fecha_ingreso' => '2026-04-17',
            'prioridad' => 100,
        ]);
    }
}
