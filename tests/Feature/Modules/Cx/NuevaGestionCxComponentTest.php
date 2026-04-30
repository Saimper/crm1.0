<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Cx;

use App\Models\User;
use App\Modules\Casos\Infrastructure\Http\Livewire\NuevaGestion;
use App\Modules\Cx\Application\DTOs\RegistrarCasoTicketCxInput;
use App\Modules\Cx\Application\UseCases\RegistrarCasoTicketCx;
use Database\Seeders\Catalogos\TiposIdentificacionSeeder;
use Database\Seeders\Cx\CategoriasTicketDemoSeeder;
use Database\Seeders\Cx\EstadosCasoCxDemoSeeder;
use Database\Seeders\Cx\GestionesCatalogosCxDemoSeeder;
use Database\Seeders\Cx\NivelesEscalamientoDemoSeeder;
use Database\Seeders\Cx\NivelesSlaDemoSeeder;
use Database\Seeders\Cx\PrioridadesTicketDemoSeeder;
use Database\Seeders\Gestiones\CanalesSeeder;
use Database\Seeders\Tenancy\CarterasDemoSeeder;
use Database\Seeders\Tenancy\MandantesDemoSeeder;
use Database\Seeders\Tenancy\ProyectosDemoSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class NuevaGestionCxComponentTest extends TestCase
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
            EstadosCasoCxDemoSeeder::class,
            CanalesSeeder::class,
            GestionesCatalogosCxDemoSeeder::class,
            CategoriasTicketDemoSeeder::class,
            PrioridadesTicketDemoSeeder::class,
            NivelesSlaDemoSeeder::class,
            NivelesEscalamientoDemoSeeder::class,
        ]);
    }

    public function test_registra_gestion_cx_con_resolucion_desde_componente(): void
    {
        [$casoId, $personaId, $proyectoId] = $this->crearContextoCx();
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));
        $this->actingAs(User::factory()->create());

        $canalId = (int) DB::table('canales')->where('codigo', 'TELEFONO')->value('id');
        $tipoGestionId = (int) DB::table('tipos_gestion')->where('proyecto_id', $proyectoId)->where('codigo', 'LLAMADA_ENTRANTE')->value('id');
        $resultadoId = (int) DB::table('resultados')->where('proyecto_id', $proyectoId)->where('codigo', 'ESCALADO')->value('id');
        $causaId = (int) DB::table('causas_gestion')->where('proyecto_id', $proyectoId)->where('codigo', 'CAIDO')->value('id');
        $escalamientoId = (int) DB::table('niveles_escalamiento')->where('proyecto_id', $proyectoId)->where('codigo', 'N2')->value('id');

        Livewire::test(NuevaGestion::class, [
            'casoId' => $casoId,
            'personaId' => $personaId,
            'tipoCaso' => 'ticket_cx',
        ])
            ->set('canalId', $canalId)
            ->set('tipoGestionId', $tipoGestionId)
            ->set('resultadoId', $resultadoId)
            ->set('causaId', $causaId)
            ->set('resolucionAccion', 'Verificar infraestructura')
            ->set('resolucionFechaLimite', '2026-04-19T10:00')
            ->set('resolucionNivelEscalamientoId', $escalamientoId)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('gestion-registrada');

        $this->assertDatabaseHas('compromisos', [
            'caso_id' => $casoId,
            'tipo_compromiso' => 'resolucion_ticket',
            'estado' => 'pendiente',
        ]);
        $compromisoId = (int) DB::table('compromisos')->where('caso_id', $casoId)->value('id');
        $this->assertDatabaseHas('compromisos_resolucion_ticket', [
            'compromiso_id' => $compromisoId,
            'accion_comprometida' => 'Verificar infraestructura',
            'nivel_escalamiento_id' => $escalamientoId,
        ]);
    }

    /** @return array{int,int,int} */
    private function crearContextoCx(): array
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
        $carteraId = (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->where('codigo', 'SOPORTE_GENERAL')->value('id');
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $estado = (int) DB::table('estados_caso')->where('proyecto_id', $proyectoId)->where('codigo', 'ABIERTO')->value('id');

        $personaId = (int) DB::table('personas')->insertGetId([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoId,
            'tipo_persona' => 'fisica', 'tipo_identificacion_id' => $tipoCed,
            'identificacion' => (string) random_int(1_000_000_000, 9_999_999_999),
            'nombres' => 'Test', 'apellidos' => 'Cx',
        ]);

        $out = $this->app->make(RegistrarCasoTicketCx::class)->execute(new RegistrarCasoTicketCxInput(
            proyectoId: $proyectoId,
            carteraId: $carteraId,
            personaId: $personaId,
            estadoCasoId: $estado,
            fechaIngreso: new DateTimeImmutable('2026-04-18'),
            prioridad: 100,
            codigoTicket: 'TKT-UI-'.Str::random(4),
            asunto: 'Test UI CX',
            descripcion: null,
            categoriaTicketId: null,
            prioridadTicketId: null,
            nivelSlaId: null,
            nivelEscalamientoId: null,
            fechaReporte: new DateTimeImmutable('2026-04-18 09:00:00'),
            fechaLimiteSla: null,
        ));

        return [$out->casoId, $personaId, $proyectoId];
    }
}
