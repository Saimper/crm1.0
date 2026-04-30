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
use Database\Seeders\Catalogos\TiposIdentificacionSeeder;
use Database\Seeders\Gestiones\CanalesSeeder;
use Database\Seeders\Tenancy\CarterasDemoSeeder;
use Database\Seeders\Tenancy\MandantesDemoSeeder;
use Database\Seeders\Tenancy\ProyectosDemoSeeder;
use Database\Seeders\Venta\EstadosCasoVentaDemoSeeder;
use Database\Seeders\Venta\EtapasEmbudoDemoSeeder;
use Database\Seeders\Venta\GestionesCatalogosVentaDemoSeeder;
use Database\Seeders\Venta\ProductosVentaDemoSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class ResolverCierreComponentTest extends TestCase
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
            EstadosCasoVentaDemoSeeder::class,
            CanalesSeeder::class,
            GestionesCatalogosVentaDemoSeeder::class,
            ProductosVentaDemoSeeder::class,
            EtapasEmbudoDemoSeeder::class,
        ]);
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
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'VENTA_DEMO_2026')->value('id');
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));

        $carteraId = (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->where('codigo', 'PREMIUM')->value('id');
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $estado = (int) DB::table('estados_caso')->where('proyecto_id', $proyectoId)->where('codigo', 'NUEVO')->value('id');

        $usuarioId = (int) DB::table('users')->insertGetId([
            'name' => 'UC', 'email' => 'uc.'.Str::random(6).'@crm.local',
            'password' => bcrypt('x'), 'activo' => true,
        ]);
        $personaId = (int) DB::table('personas')->insertGetId([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoId,
            'tipo_persona' => 'fisica', 'tipo_identificacion_id' => $tipoCed,
            'identificacion' => (string) random_int(1_000_000_000, 9_999_999_999),
            'nombres' => 'Tester', 'apellidos' => 'Venta',
        ]);

        $out = $this->app->make(RegistrarCasoLeadVenta::class)->execute(new RegistrarCasoLeadVentaInput(
            proyectoId: $proyectoId,
            carteraId: $carteraId,
            personaId: $personaId,
            estadoCasoId: $estado,
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
            proyectoId: $proyectoId,
            casoId: $out->casoId,
            personaId: $personaId,
            contactoId: null,
            canalId: (int) DB::table('canales')->where('codigo', 'TELEFONO')->value('id'),
            tipoGestionId: (int) DB::table('tipos_gestion')->where('proyecto_id', $proyectoId)->where('codigo', 'LLAMADA_SALIENTE')->value('id'),
            resultadoId: (int) DB::table('resultados')->where('proyecto_id', $proyectoId)->where('codigo', 'PROMESA_CIERRE')->value('id'),
            motivoNoContactoId: null,
            causaId: null,
            usuarioId: $usuarioId,
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
