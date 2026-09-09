<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Servicio;

use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\UseCases\RegistrarGestion;
use App\Modules\Servicio\Application\DTOs\RegistrarCasoServicioInput;
use App\Modules\Servicio\Application\UseCases\RegistrarCasoServicio;
use App\Modules\Servicio\Domain\ValueObjects\DatosAccionServicio;
use App\Modules\Servicio\Domain\ValueObjects\DescripcionAccion;
use App\Modules\Servicio\Domain\ValueObjects\FechaProgramada;
use App\Modules\Servicio\Infrastructure\Http\Livewire\ResolverAccion;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class ResolverAccionComponentTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_marca_accion_ejecutada_desde_componente(): void
    {
        $proyecto = $this->crearProyectoServicio();
        $this->activarProyecto($proyecto);

        $compromisoId = $this->crearContextoConAccion($proyecto);
        $this->actingAs($this->crearGestor($proyecto));

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

    private function crearContextoConAccion(stdClass $proyecto): int
    {
        $proyectoId = (int) $proyecto->id;

        $cartera = $this->crearCarteraEn($proyecto, 'RESIDENCIAL');
        $estado = $this->crearEstadoCasoEn($proyecto, 'PENDIENTE');
        $persona = $this->crearPersonaEn($proyecto);
        $usuario = $this->crearGestor($proyecto);

        $cascada = $this->crearCascadaGestionEn($proyecto, [
            'requiere_compromiso' => true,
            'codigo_tipo' => 'COORDINACION',
            'codigo_resultado' => 'AGENDADO',
        ]);

        $out = $this->app->make(RegistrarCasoServicio::class)->execute(new RegistrarCasoServicioInput(
            proyectoId: $proyectoId,
            carteraId: (int) $cartera->id,
            personaId: (int) $persona->id,
            estadoCasoId: (int) $estado->id,
            fechaIngreso: new DateTimeImmutable('2026-04-20'),
            prioridad: 100,
            codigoServicio: 'SVC-RES-'.Str::random(4),
            tipoAccionServicioId: null,
            estadoTecnicoId: null,
            direccionServicio: null,
            tecnicoAsignado: null,
            fechaSolicitud: new DateTimeImmutable('2026-04-20'),
            fechaProgramada: null,
        ));

        $this->app->make(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId: (string) Str::ulid(),
            proyectoId: $proyectoId,
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
            creadaEn: new DateTimeImmutable('2026-04-20 10:00:00'),
            datosCompromiso: new DatosAccionServicio(
                descripcion: new DescripcionAccion('Instalación demo'),
                fechaProgramada: new FechaProgramada(new DateTimeImmutable('2026-04-25 10:00:00')),
            ),
        ));

        return (int) DB::table('compromisos')->where('caso_id', $out->casoId)->value('id');
    }
}
