<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Cobranza;

use App\Modules\Casos\Infrastructure\Http\Livewire\NuevaGestion;
use App\Modules\Cobranza\Application\DTOs\RegistrarCasoCobranzaInput;
use App\Modules\Cobranza\Application\UseCases\RegistrarCasoCobranza;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class NuevaGestionComponentTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_registra_gestion_con_promesa_desde_componente_livewire(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);

        [$casoId, $personaId] = $this->crearCasoCobranza($proyecto);

        $cascada = $this->crearCascadaGestionEn($proyecto, [
            'requiere_compromiso' => true,
            'requiere_causa' => true,
        ]);
        $tipoPagoId = $this->crearTipoPagoEn($proyecto);

        $this->actingAs($this->crearGestor($proyecto));

        Livewire::test(NuevaGestion::class, [
            'casoId' => $casoId,
            'personaId' => $personaId,
            'tipoCaso' => 'cobranza',
        ])
            ->set('canalId', $cascada['canal_id'])
            ->set('tipoGestionId', $cascada['tipo_gestion_id'])
            ->set('resultadoId', $cascada['resultado_id'])
            ->set('causaId', $cascada['causa_id'])
            ->set('promesaMonto', '750.50')
            ->set('promesaFecha', Carbon::today()->addDays(10)->toDateString())
            ->set('promesaTipoPagoId', $tipoPagoId)
            ->set('notas', 'Promesa registrada desde el componente.')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('gestion-registrada');

        $this->assertDatabaseHas('gestiones', [
            'caso_id' => $casoId,
            'resultado_id' => $cascada['resultado_id'],
            'causa_id' => $cascada['causa_id'],
        ]);
        $this->assertDatabaseHas('compromisos', [
            'caso_id' => $casoId,
            'tipo_compromiso' => 'promesa_pago',
            'estado' => 'pendiente',
        ]);
        $compromisoId = (int) DB::table('compromisos')->where('caso_id', $casoId)->value('id');
        $this->assertDatabaseHas('compromisos_promesa_pago', [
            'compromiso_id' => $compromisoId,
            'monto' => '750.50',
            'tipo_pago_id' => $tipoPagoId,
        ]);
    }

    public function test_valida_que_resultado_es_requerido(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);

        [$casoId, $personaId] = $this->crearCasoCobranza($proyecto);
        $cascada = $this->crearCascadaGestionEn($proyecto);

        $this->actingAs($this->crearGestor($proyecto));

        Livewire::test(NuevaGestion::class, [
            'casoId' => $casoId,
            'personaId' => $personaId,
            'tipoCaso' => 'cobranza',
        ])
            ->set('canalId', $cascada['canal_id'])
            ->set('tipoGestionId', $cascada['tipo_gestion_id'])
            ->call('guardar')
            ->assertHasErrors(['resultadoId']);
    }

    /** @return array{int,int} */
    private function crearCasoCobranza(stdClass $proyecto): array
    {
        $cartera = $this->crearCarteraEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto, 'ABIERTO');

        $out = $this->app->make(RegistrarCasoCobranza::class)->execute(new RegistrarCasoCobranzaInput(
            proyectoId: (int) $proyecto->id,
            carteraId: (int) $cartera->id,
            personaId: (int) $persona->id,
            estadoCasoId: (int) $estado->id,
            fechaIngreso: new DateTimeImmutable('2026-04-17'),
            prioridad: 100,
            numeroPrestamo: 'PRST-UI-'.Str::random(4),
            moneda: 'USD',
            montoOriginal: '3000.00',
            saldoCapital: '2500.00',
            saldoInteres: '50.00',
            saldoTotal: '2550.00',
            cuotaMensual: '250.00',
            cuotasTotales: 12,
            cuotasPagadas: 2,
            diasMora: 10,
            fechaDesembolso: new DateTimeImmutable('2026-01-01'),
            fechaVencimiento: new DateTimeImmutable('2027-01-01'),
        ));

        return [$out->casoId, (int) $persona->id];
    }
}
