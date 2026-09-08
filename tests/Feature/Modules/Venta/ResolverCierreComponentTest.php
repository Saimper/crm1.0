<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Venta;

use App\Models\User;
use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\UseCases\RegistrarGestion;
use App\Modules\Venta\Application\DTOs\RegistrarCasoLeadVentaInput;
use App\Modules\Venta\Application\UseCases\RegistrarCasoLeadVenta;
use App\Modules\Venta\Domain\ValueObjects\DatosCierreVenta;
use App\Modules\Venta\Domain\ValueObjects\FechaCierreEstimada;
use App\Modules\Venta\Domain\ValueObjects\MontoCierre;
use App\Modules\Venta\Infrastructure\Http\Livewire\ResolverCierre;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class ResolverCierreComponentTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_marca_cierre_ganado_desde_componente(): void
    {
        $compromisoId = $this->crearContextoConCierre();
        $this->actingAs(User::factory()->create());

        Livewire::test(ResolverCierre::class, ['compromisoId' => $compromisoId])
            ->call('abrir', 'ganado')
            ->assertSet('modalAbierto', true)
            ->set('fechaResolucion', '2026-05-05')
            ->call('confirmar')
            ->assertHasNoErrors()
            ->assertDispatched('compromiso-resuelto')
            ->assertSet('modalAbierto', false);

        $this->assertDatabaseHas('compromisos', ['id' => $compromisoId, 'estado' => 'cumplido']);
    }

    private function crearContextoConCierre(): int
    {
        $proyecto = $this->crearProyectoVenta();
        $this->activarProyecto($proyecto);

        $cartera = $this->crearCarteraEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto, 'NUEVO');
        $persona = $this->crearPersonaEn($proyecto);
        $usuario = $this->crearGestor($proyecto);
        $cascada = $this->crearCascadaGestionEn($proyecto, ['requiere_compromiso' => true]);

        $out = $this->app->make(RegistrarCasoLeadVenta::class)->execute(new RegistrarCasoLeadVentaInput(
            proyectoId: (int) $proyecto->id,
            carteraId: (int) $cartera->id,
            personaId: (int) $persona->id,
            estadoCasoId: (int) $estado->id,
            fechaIngreso: new DateTimeImmutable('2026-04-18'),
            prioridad: 100,
            codigoLead: 'LEAD-RES-'.Str::random(4),
            productoVentaId: null,
            etapaEmbudoId: null,
            valorEstimadoMonto: '1500.00',
            moneda: 'USD',
            origenLead: null,
            fechaPrimerContacto: new DateTimeImmutable('2026-04-18'),
            fechaEstimadaCierre: null,
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
            causaId: null,
            usuarioId: (int) $usuario->id,
            notas: null,
            duracion: null,
            creadaEn: new DateTimeImmutable('2026-04-18 10:00:00'),
            datosCompromiso: new DatosCierreVenta(
                monto: new MontoCierre('2000.00'),
                fechaEstimada: new FechaCierreEstimada(new DateTimeImmutable('2026-05-10')),
            ),
        ));

        return (int) DB::table('compromisos')->where('caso_id', $out->casoId)->value('id');
    }
}
