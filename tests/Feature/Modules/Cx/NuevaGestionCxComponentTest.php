<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Cx;

use App\Modules\Casos\Infrastructure\Http\Livewire\NuevaGestion;
use App\Modules\Cx\Application\DTOs\RegistrarCasoTicketCxInput;
use App\Modules\Cx\Application\UseCases\RegistrarCasoTicketCx;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class NuevaGestionCxComponentTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_registra_gestion_cx_con_resolucion_desde_componente(): void
    {
        $proyecto = $this->crearProyectoCx();
        [$casoId, $personaId] = $this->crearContextoCx($proyecto);
        $this->activarProyecto($proyecto);

        // El resultado exige compromiso (abre el slot de resolución del ticket)
        // y exige causa, que es lo que el formulario original rellenaba con
        // `CAIDO`.
        $cascada = $this->crearCascadaGestionEn($proyecto, [
            'requiere_compromiso' => true,
            'requiere_causa' => true,
        ]);
        $escalamientoId = $this->crearNivelEscalamientoEn($proyecto);

        // Registrar una gestión exige `gestiones.crear` desde F42: el usuario
        // anónimo de factory que montaba este test ya no pasa la puerta.
        $gestor = $this->crearGestor($proyecto);

        $fechaLimite = Carbon::now()->addDay()->format('Y-m-d\TH:i');

        Livewire::actingAs($gestor)
            ->test(NuevaGestion::class, [
                'casoId' => $casoId,
                'personaId' => $personaId,
                'tipoCaso' => 'ticket_cx',
            ])
            ->set('canalId', $cascada['canal_id'])
            ->set('tipoGestionId', $cascada['tipo_gestion_id'])
            ->set('resultadoId', $cascada['resultado_id'])
            ->set('causaId', $cascada['causa_id'])
            ->set('resolucionAccion', 'Verificar infraestructura')
            ->set('resolucionFechaLimite', $fechaLimite)
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

    /** @return array{int,int} */
    private function crearContextoCx(stdClass $proyecto): array
    {
        $cartera = $this->crearCarteraEn($proyecto, 'SOPORTE_GENERAL');
        $persona = $this->crearPersonaEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto, 'ABIERTO');

        $out = $this->app->make(RegistrarCasoTicketCx::class)->execute(new RegistrarCasoTicketCxInput(
            proyectoId: (int) $proyecto->id,
            carteraId: (int) $cartera->id,
            personaId: (int) $persona->id,
            estadoCasoId: (int) $estado->id,
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

        return [$out->casoId, (int) $persona->id];
    }

    /** No hay helper en `EscenarioOperativo` para este catálogo tipo-específico de CX. */
    private function crearNivelEscalamientoEn(stdClass $proyecto): int
    {
        $ahora = Carbon::now();

        return (int) DB::table('niveles_escalamiento')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'N2',
            'nombre' => 'Nivel 2',
            'nivel' => 2,
            'activo' => true,
            'orden' => 20,
            'creada_en' => $ahora,
            'actualizada_en' => $ahora,
        ]);
    }
}
