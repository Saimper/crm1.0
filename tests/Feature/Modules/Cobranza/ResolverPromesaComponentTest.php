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
use Database\Seeders\Casos\EstadosCasoDemoSeeder;
use Database\Seeders\Catalogos\TiposIdentificacionSeeder;
use Database\Seeders\Cobranza\CausasMoraDemoSeeder;
use Database\Seeders\Cobranza\TiposPagoDemoSeeder;
use Database\Seeders\Cobranza\TramosMoraDemoSeeder;
use Database\Seeders\Gestiones\CanalesSeeder;
use Database\Seeders\Gestiones\GestionesCatalogosDemoSeeder;
use Database\Seeders\Tenancy\CarterasDemoSeeder;
use Database\Seeders\Tenancy\MandantesDemoSeeder;
use Database\Seeders\Tenancy\ProyectosDemoSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class ResolverPromesaComponentTest extends TestCase
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
            CanalesSeeder::class,
            GestionesCatalogosDemoSeeder::class,
            TramosMoraDemoSeeder::class,
            TiposPagoDemoSeeder::class,
            CausasMoraDemoSeeder::class,
        ]);
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
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));

        $carteraId = (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->where('codigo', 'CONSUMO')->value('id');
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $estado = (int) DB::table('estados_caso')->where('proyecto_id', $proyectoId)->where('codigo', 'ABIERTO')->value('id');

        $usuarioId = (int) DB::table('users')->insertGetId([
            'name' => 'UC', 'email' => 'uc.'.Str::random(6).'@crm.local',
            'password' => bcrypt('x'), 'activo' => true,
        ]);
        $personaId = (int) DB::table('personas')->insertGetId([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoId,
            'tipo_persona' => 'fisica', 'tipo_identificacion_id' => $tipoCed,
            'identificacion' => (string) random_int(1_000_000_000, 9_999_999_999),
            'nombres' => 'Test', 'apellidos' => 'User',
        ]);

        $out = $this->app->make(RegistrarCasoCobranza::class)->execute(new RegistrarCasoCobranzaInput(
            proyectoId: $proyectoId,
            carteraId: $carteraId,
            personaId: $personaId,
            estadoCasoId: $estado,
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
            proyectoId: $proyectoId,
            casoId: $out->casoId,
            personaId: $personaId,
            contactoId: null,
            canalId: (int) DB::table('canales')->where('codigo', 'TELEFONO')->value('id'),
            tipoGestionId: (int) DB::table('tipos_gestion')->where('proyecto_id', $proyectoId)->where('codigo', 'LLAMADA_SALIENTE')->value('id'),
            resultadoId: (int) DB::table('resultados')->where('proyecto_id', $proyectoId)->where('codigo', 'PROMESA_PAGO')->value('id'),
            motivoNoContactoId: null,
            causaId: (int) DB::table('causas_gestion')->where('proyecto_id', $proyectoId)->where('codigo', 'DESEMPLEO')->value('id'),
            usuarioId: $usuarioId,
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
