<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Cobranza;

use App\Models\User;
use App\Modules\Cobranza\Application\DTOs\RegistrarCasoCobranzaInput;
use App\Modules\Cobranza\Application\UseCases\RegistrarCasoCobranza;
use App\Modules\Cobranza\Domain\ValueObjects\DatosPromesaPago;
use App\Modules\Cobranza\Domain\ValueObjects\FechaPromesa;
use App\Modules\Cobranza\Domain\ValueObjects\MontoPromesa;
use App\Modules\Cobranza\Infrastructure\Http\Livewire\ResolverPromesa;
use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\UseCases\RegistrarGestion;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class ResolverPromesaComponentTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_marca_promesa_cumplida_desde_componente(): void
    {
        $compromisoId = $this->crearContextoConPromesa();
        $this->actingAs(User::factory()->create());

        Livewire::test(ResolverPromesa::class, ['compromisoId' => $compromisoId])
            ->call('abrir', 'cumplida')
            ->assertSet('modalAbierto', true)
            ->set('fechaResolucion', '2026-04-24')
            ->call('confirmar')
            ->assertHasNoErrors()
            ->assertDispatched('compromiso-resuelto')
            ->assertSet('modalAbierto', false);

        $this->assertDatabaseHas('compromisos', [
            'id' => $compromisoId,
            'estado' => 'cumplido',
        ]);
    }

    public function test_valida_fecha_resolucion_requerida(): void
    {
        $compromisoId = $this->crearContextoConPromesa();
        $this->actingAs(User::factory()->create());

        Livewire::test(ResolverPromesa::class, ['compromisoId' => $compromisoId])
            ->call('abrir', 'rota')
            ->set('fechaResolucion', '')
            ->call('confirmar')
            ->assertHasErrors(['fechaResolucion']);
    }

    private function crearContextoConPromesa(): int
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);

        $cartera = $this->crearCarteraEn($proyecto, 'CONSUMO');
        $persona = $this->crearPersonaEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto, 'ABIERTO');
        $usuario = $this->crearGestor($proyecto);

        $cascada = $this->crearCascadaGestionEn($proyecto, [
            'requiere_compromiso' => true,
            'requiere_causa' => true,
            'codigo_tipo' => 'LLAMADA_SALIENTE',
            'codigo_resultado' => 'PROMESA_PAGO',
        ]);

        $out = $this->app->make(RegistrarCasoCobranza::class)->execute(new RegistrarCasoCobranzaInput(
            proyectoId: (int) $proyecto->id,
            carteraId: (int) $cartera->id,
            personaId: (int) $persona->id,
            estadoCasoId: (int) $estado->id,
            fechaIngreso: new DateTimeImmutable('2026-04-17'),
            prioridad: 100,
            numeroPrestamo: 'PRST-RES-'.Str::random(4),
            moneda: 'USD',
            montoOriginal: '2000.00',
            saldoCapital: '1800.00',
            saldoInteres: '20.00',
            saldoTotal: '1820.00',
            cuotaMensual: '200.00',
            cuotasTotales: 10,
            cuotasPagadas: 1,
            diasMora: 5,
            fechaDesembolso: new DateTimeImmutable('2026-02-01'),
            fechaVencimiento: new DateTimeImmutable('2026-12-01'),
        ));

        $this->app->make(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId: (string) Str::ulid(),
            proyectoId: (int) $proyecto->id,
            casoId: $out->casoId,
            personaId: (int) $persona->id,
            contactoId: null,
            canalId: $cascada['canal_id'],
            tipoGestionId: $cascada['tipo_gestion_id'],
            resultadoId: $cascada['resultado_id'],
            motivoNoContactoId: null,
            causaId: $cascada['causa_id'],
            usuarioId: (int) $usuario->id,
            notas: null,
            duracion: null,
            creadaEn: new DateTimeImmutable('2026-04-17 10:00:00'),
            datosCompromiso: new DatosPromesaPago(
                monto: new MontoPromesa('500.00'),
                fechaVencimiento: new FechaPromesa(new DateTimeImmutable('2026-04-24')),
            ),
        ));

        return (int) DB::table('compromisos')->where('caso_id', $out->casoId)->value('id');
    }
}
