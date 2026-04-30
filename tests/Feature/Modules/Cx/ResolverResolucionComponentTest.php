<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Cx;

use App\Models\User;
use App\Modules\Cx\Application\DTOs\RegistrarCasoTicketCxInput;
use App\Modules\Cx\Application\UseCases\RegistrarCasoTicketCx;
use App\Modules\Cx\Domain\ValueObjects\AccionComprometida;
use App\Modules\Cx\Domain\ValueObjects\DatosResolucionTicket;
use App\Modules\Cx\Domain\ValueObjects\FechaLimiteSla;
use App\Modules\Cx\Infrastructure\Http\Livewire\ResolverResolucion;
use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\UseCases\RegistrarGestion;
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

final class ResolverResolucionComponentTest extends TestCase
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

    public function test_marca_resolucion_cumplida_desde_componente(): void
    {
        $compromisoId = $this->crearContextoConResolucion();
        $this->actingAs(User::factory()->create());

        Livewire::test(ResolverResolucion::class, ['compromisoId' => $compromisoId])
            ->call('abrir', 'cumplida')
            ->assertSet('modalAbierto', true)
            ->set('fechaResolucion', '2026-04-19')
            ->call('confirmar')
            ->assertHasNoErrors()
            ->assertDispatched('compromiso-resuelto')
            ->assertSet('modalAbierto', false);

        $this->assertDatabaseHas('compromisos', ['id' => $compromisoId, 'estado' => 'cumplido']);
    }

    private function crearContextoConResolucion(): int
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));

        $carteraId = (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->where('codigo', 'SOPORTE_GENERAL')->value('id');
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
            'nombres' => 'Test', 'apellidos' => 'Cx',
        ]);

        $out = $this->app->make(RegistrarCasoTicketCx::class)->execute(new RegistrarCasoTicketCxInput(
            proyectoId: $proyectoId,
            carteraId: $carteraId,
            personaId: $personaId,
            estadoCasoId: $estado,
            fechaIngreso: new DateTimeImmutable('2026-04-18'),
            prioridad: 100,
            codigoTicket: 'TKT-RES-'.Str::random(4),
            asunto: 'Ticket resolución',
            descripcion: null,
            categoriaTicketId: null,
            prioridadTicketId: null,
            nivelSlaId: null,
            nivelEscalamientoId: null,
            fechaReporte: new DateTimeImmutable('2026-04-18 09:00:00'),
            fechaLimiteSla: null,
        ));

        $this->app->make(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId: (string) Str::ulid(),
            proyectoId: $proyectoId,
            casoId: $out->casoId,
            personaId: $personaId,
            contactoId: null,
            canalId: (int) DB::table('canales')->where('codigo', 'TELEFONO')->value('id'),
            tipoGestionId: (int) DB::table('tipos_gestion')->where('proyecto_id', $proyectoId)->where('codigo', 'LLAMADA_ENTRANTE')->value('id'),
            resultadoId: (int) DB::table('resultados')->where('proyecto_id', $proyectoId)->where('codigo', 'COMPROMISO_SLA')->value('id'),
            motivoNoContactoId: null,
            causaId: null,
            usuarioId: $usuarioId,
            notas: null,
            duracion: null,
            creadaEn: new DateTimeImmutable('2026-04-18 10:00:00'),
            datosCompromiso: new DatosResolucionTicket(
                accion: new AccionComprometida('Atender a brevedad'),
                fechaLimite: new FechaLimiteSla(new DateTimeImmutable('2026-04-19 10:00:00')),
            ),
        ));

        return (int) DB::table('compromisos')->where('caso_id', $out->casoId)->value('id');
    }
}
