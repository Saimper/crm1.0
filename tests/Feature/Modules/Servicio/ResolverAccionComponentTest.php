<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Servicio;

use App\Models\User;
use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\UseCases\RegistrarGestion;
use App\Modules\Servicio\Application\DTOs\RegistrarCasoServicioInput;
use App\Modules\Servicio\Application\UseCases\RegistrarCasoServicio;
use App\Modules\Servicio\Domain\ValueObjects\DatosAccionServicio;
use App\Modules\Servicio\Domain\ValueObjects\DescripcionAccion;
use App\Modules\Servicio\Domain\ValueObjects\FechaProgramada;
use App\Modules\Servicio\Infrastructure\Http\Livewire\ResolverAccion;
use Database\Seeders\Catalogos\TiposIdentificacionSeeder;
use Database\Seeders\Gestiones\CanalesSeeder;
use Database\Seeders\Servicio\EstadosCasoServicioDemoSeeder;
use Database\Seeders\Servicio\EstadosTecnicosDemoSeeder;
use Database\Seeders\Servicio\GestionesCatalogosServicioDemoSeeder;
use Database\Seeders\Servicio\TiposAccionServicioDemoSeeder;
use Database\Seeders\Tenancy\CarterasDemoSeeder;
use Database\Seeders\Tenancy\MandantesDemoSeeder;
use Database\Seeders\Tenancy\ProyectosDemoSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class ResolverAccionComponentTest extends TestCase
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
            EstadosCasoServicioDemoSeeder::class,
            CanalesSeeder::class,
            GestionesCatalogosServicioDemoSeeder::class,
            TiposAccionServicioDemoSeeder::class,
            EstadosTecnicosDemoSeeder::class,
        ]);
    }

    public function test_marca_accion_ejecutada_desde_componente(): void
    {
        $compromisoId = $this->crearContextoConAccion();
        $this->actingAs(User::factory()->create());

        Livewire::test(ResolverAccion::class, ['compromisoId' => $compromisoId])
            ->call('abrir', 'ejecutada')
            ->assertSet('modalAbierto', true)
            ->set('fechaResolucion', '2026-04-25')
            ->call('confirmar')
            ->assertHasNoErrors()
            ->assertDispatched('compromiso-resuelto')
            ->assertSet('modalAbierto', false);

        $this->assertDatabaseHas('compromisos', ['id' => $compromisoId, 'estado' => 'cumplido']);
    }

    private function crearContextoConAccion(): int
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'SERVICIO_DEMO_2026')->value('id');
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));

        $carteraId  = (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->where('codigo', 'RESIDENCIAL')->value('id');
        $tipoCed    = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $estado     = (int) DB::table('estados_caso')->where('proyecto_id', $proyectoId)->where('codigo', 'PENDIENTE')->value('id');

        $usuarioId = (int) DB::table('users')->insertGetId([
            'name' => 'UC', 'email' => 'uc.'.Str::random(6).'@crm.local',
            'password' => bcrypt('x'), 'activo' => true,
        ]);
        $personaId = (int) DB::table('personas')->insertGetId([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoId,
            'tipo_persona' => 'fisica', 'tipo_identificacion_id' => $tipoCed,
            'identificacion' => (string) random_int(1_000_000_000, 9_999_999_999),
            'nombres' => 'Tester', 'apellidos' => 'Servicio',
        ]);

        $out = $this->app->make(RegistrarCasoServicio::class)->execute(new RegistrarCasoServicioInput(
            proyectoId:           $proyectoId,
            carteraId:            $carteraId,
            personaId:            $personaId,
            estadoCasoId:         $estado,
            fechaIngreso:         new DateTimeImmutable('2026-04-20'),
            prioridad:            100,
            codigoServicio:       'SVC-RES-'.Str::random(4),
            tipoAccionServicioId: null,
            estadoTecnicoId:      null,
            direccionServicio:    null,
            tecnicoAsignado:      null,
            fechaSolicitud:       new DateTimeImmutable('2026-04-20'),
            fechaProgramada:      null,
        ));

        $this->app->make(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId:          (string) Str::ulid(),
            proyectoId:        $proyectoId,
            casoId:            $out->casoId,
            personaId:         $personaId,
            contactoId:        null,
            canalId:           (int) DB::table('canales')->where('codigo', 'TELEFONO')->value('id'),
            tipoGestionId:     (int) DB::table('tipos_gestion')->where('proyecto_id', $proyectoId)->where('codigo', 'COORDINACION')->value('id'),
            resultadoId:       (int) DB::table('resultados')->where('proyecto_id', $proyectoId)->where('codigo', 'AGENDADO')->value('id'),
            motivoNoContactoId: null,
            causaId:           null,
            usuarioId:         $usuarioId,
            notas:             null,
            duracion:          null,
            creadaEn:          new DateTimeImmutable('2026-04-20 10:00:00'),
            datosCompromiso:   new DatosAccionServicio(
                descripcion:     new DescripcionAccion('Instalación demo'),
                fechaProgramada: new FechaProgramada(new DateTimeImmutable('2026-04-25 10:00:00')),
            ),
        ));

        return (int) DB::table('compromisos')->where('caso_id', $out->casoId)->value('id');
    }
}
