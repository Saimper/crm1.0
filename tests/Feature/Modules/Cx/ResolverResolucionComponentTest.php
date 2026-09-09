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
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class ResolverResolucionComponentTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    /**
     * El gestor que monta el escenario. Antes estos tests actuaban como un
     * `User::factory()` sin rol en el proyecto y pasaban igual, porque el
     * componente no comprobaba permisos. Ahora sí, y el actor tiene que ser
     * quien de verdad puede resolver.
     */
    private ?User $gestor = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_marca_resolucion_cumplida_desde_componente(): void
    {
        $compromisoId = $this->crearContextoConResolucion();
        $this->actingAs($this->gestor);

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

    /**
     * Un proyecto CX con un ticket abierto y una gestión que deja un compromiso
     * de resolución vigente: es lo que el componente resuelve. La cascada exige
     * compromiso porque sin esa bandera el listener `CrearResolucionDesdeGestion`
     * no llega a crear nada.
     */
    private function crearContextoConResolucion(): int
    {
        $proyecto = $this->crearProyectoCx();
        $this->activarProyecto($proyecto);

        $cartera = $this->crearCarteraEn($proyecto, 'SOPORTE_GENERAL');
        $estado = $this->crearEstadoCasoEn($proyecto, 'ABIERTO');
        $persona = $this->crearPersonaEn($proyecto);
        $usuario = $this->gestor = $this->crearGestor($proyecto);

        $cascada = $this->crearCascadaGestionEn($proyecto, [
            'codigo_tipo' => 'LLAMADA_ENTRANTE',
            'codigo_resultado' => 'COMPROMISO_SLA',
            'requiere_compromiso' => true,
        ]);

        $out = $this->app->make(RegistrarCasoTicketCx::class)->execute(new RegistrarCasoTicketCxInput(
            proyectoId: (int) $proyecto->id,
            carteraId: (int) $cartera->id,
            personaId: (int) $persona->id,
            estadoCasoId: (int) $estado->id,
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
            datosCompromiso: new DatosResolucionTicket(
                accion: new AccionComprometida('Atender a brevedad'),
                fechaLimite: new FechaLimiteSla(new DateTimeImmutable('2026-04-19 10:00:00')),
            ),
        ));

        return (int) DB::table('compromisos')->where('caso_id', $out->casoId)->value('id');
    }
}
