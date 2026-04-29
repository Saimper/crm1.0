<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Cobranza;

use App\Models\User;
use App\Modules\Casos\Infrastructure\Http\Livewire\NuevaGestion;
use App\Modules\Cobranza\Application\DTOs\RegistrarCasoCobranzaInput;
use App\Modules\Cobranza\Application\UseCases\RegistrarCasoCobranza;
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

final class NuevaGestionComponentTest extends TestCase
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

    public function test_registra_gestion_con_promesa_desde_componente_livewire(): void
    {
        [$casoId, $personaId, $proyectoId] = $this->crearCasoCobranza();
        $this->bindProyecto($proyectoId);

        $gestor = $this->crearGestor();
        $this->actingAs($gestor);

        $canalId        = (int) DB::table('canales')->where('codigo', 'TELEFONO')->value('id');
        $tipoGestionId  = (int) DB::table('tipos_gestion')->where('proyecto_id', $proyectoId)->where('codigo', 'LLAMADA_SALIENTE')->value('id');
        $resultadoId    = (int) DB::table('resultados')->where('proyecto_id', $proyectoId)->where('codigo', 'PROMESA_PAGO')->value('id');
        $causaId        = (int) DB::table('causas_gestion')->where('proyecto_id', $proyectoId)->where('codigo', 'DESEMPLEO')->value('id');
        $tipoPagoId     = (int) DB::table('tipos_pago')->where('proyecto_id', $proyectoId)->where('codigo', 'TRANSFERENCIA')->value('id');

        Livewire::test(NuevaGestion::class, [
                'casoId'    => $casoId,
                'personaId' => $personaId,
                'tipoCaso'  => 'cobranza',
            ])
            ->set('canalId', $canalId)
            ->set('tipoGestionId', $tipoGestionId)
            ->set('resultadoId', $resultadoId)
            ->set('causaId', $causaId)
            ->set('promesaMonto', '750.50')
            ->set('promesaFecha', '2026-04-25')
            ->set('promesaTipoPagoId', $tipoPagoId)
            ->set('notas', 'Promesa registrada desde el componente.')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('gestion-registrada');

        $this->assertDatabaseHas('gestiones', [
            'caso_id'      => $casoId,
            'resultado_id' => $resultadoId,
            'causa_id'     => $causaId,
        ]);
        $this->assertDatabaseHas('compromisos', [
            'caso_id'         => $casoId,
            'tipo_compromiso' => 'promesa_pago',
            'estado'          => 'pendiente',
        ]);
        $compromisoId = (int) DB::table('compromisos')->where('caso_id', $casoId)->value('id');
        $this->assertDatabaseHas('compromisos_promesa_pago', [
            'compromiso_id' => $compromisoId,
            'monto'         => '750.50',
            'tipo_pago_id'  => $tipoPagoId,
        ]);
    }

    public function test_valida_que_resultado_es_requerido(): void
    {
        [$casoId, $personaId, $proyectoId] = $this->crearCasoCobranza();
        $this->bindProyecto($proyectoId);
        $this->actingAs($this->crearGestor());

        $canalId        = (int) DB::table('canales')->where('codigo', 'TELEFONO')->value('id');
        $tipoGestionId  = (int) DB::table('tipos_gestion')->where('proyecto_id', $proyectoId)->where('codigo', 'LLAMADA_SALIENTE')->value('id');

        Livewire::test(NuevaGestion::class, [
                'casoId'    => $casoId,
                'personaId' => $personaId,
                'tipoCaso'  => 'cobranza',
            ])
            ->set('canalId', $canalId)
            ->set('tipoGestionId', $tipoGestionId)
            ->call('guardar')
            ->assertHasErrors(['resultadoId']);
    }

    /** @return array{int,int,int} */
    private function crearCasoCobranza(): array
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $carteraId  = (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->where('codigo', 'CONSUMO')->value('id');
        $tipoCed    = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $estadoAbiertoId = (int) DB::table('estados_caso')
            ->where('proyecto_id', $proyectoId)->where('codigo', 'ABIERTO')->value('id');

        $personaId = (int) DB::table('personas')->insertGetId([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoId,
            'tipo_persona' => 'fisica', 'tipo_identificacion_id' => $tipoCed,
            'identificacion' => (string) random_int(1_000_000_000, 9_999_999_999),
            'nombres' => 'Test', 'apellidos' => 'User',
        ]);

        $out = $this->app->make(RegistrarCasoCobranza::class)->execute(new RegistrarCasoCobranzaInput(
            proyectoId:       $proyectoId,
            carteraId:        $carteraId,
            personaId:        $personaId,
            estadoCasoId:     $estadoAbiertoId,
            fechaIngreso:     new DateTimeImmutable('2026-04-17'),
            prioridad:        100,
            numeroPrestamo:   'PRST-UI-'.Str::random(4),
            moneda:           'USD',
            montoOriginal:    '3000.00',
            saldoCapital:     '2500.00',
            saldoInteres:     '50.00',
            saldoTotal:       '2550.00',
            cuotaMensual:     '250.00',
            cuotasTotales:    12,
            cuotasPagadas:    2,
            diasMora:         10,
            fechaDesembolso:  new DateTimeImmutable('2026-01-01'),
            fechaVencimiento: new DateTimeImmutable('2027-01-01'),
        ));

        return [$out->casoId, $personaId, $proyectoId];
    }

    private function crearGestor(): User
    {
        return User::factory()->create();
    }

    private function bindProyecto(int $proyectoId): void
    {
        $proyecto = DB::table('proyectos')->where('id', $proyectoId)->first();
        $this->app->instance('tenancy.proyecto_activo', $proyecto);
    }
}
