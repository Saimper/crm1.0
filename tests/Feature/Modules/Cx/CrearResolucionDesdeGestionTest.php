<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Cx;

use App\Modules\Compromisos\Application\DTOs\ResolverCompromisoInput;
use App\Modules\Cx\Application\DTOs\RegistrarCasoTicketCxInput;
use App\Modules\Cx\Application\UseCases\CancelarResolucion;
use App\Modules\Cx\Application\UseCases\MarcarResolucionCumplida;
use App\Modules\Cx\Application\UseCases\MarcarResolucionRota;
use App\Modules\Cx\Application\UseCases\RegistrarCasoTicketCx;
use App\Modules\Cx\Domain\ValueObjects\AccionComprometida;
use App\Modules\Cx\Domain\ValueObjects\DatosResolucionTicket;
use App\Modules\Cx\Domain\ValueObjects\FechaLimiteSla;
use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\UseCases\RegistrarGestion;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class CrearResolucionDesdeGestionTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_registrar_gestion_con_escalamiento_crea_compromiso_y_resolucion(): void
    {
        $ctx = $this->contexto();

        $this->app->make(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId: (string) Str::ulid(),
            proyectoId: $ctx['proyectoId'],
            casoId: $ctx['casoId'],
            personaId: $ctx['personaId'],
            contactoId: null,
            canalId: $ctx['escalado']['canal_id'],
            tipoGestionId: $ctx['escalado']['tipo_gestion_id'],
            resultadoId: $ctx['escalado']['resultado_id'],
            motivoNoContactoId: null,
            causaId: $ctx['escalado']['causa_id'],
            usuarioId: $ctx['usuarioId'],
            notas: 'Cliente reporta caída general, se escala a nivel 2.',
            duracion: null,
            creadaEn: new DateTimeImmutable('2026-04-18 10:00:00'),
            datosCompromiso: new DatosResolucionTicket(
                accion: new AccionComprometida('Revisión de infraestructura y respuesta al cliente'),
                fechaLimite: new FechaLimiteSla(new DateTimeImmutable('2026-04-19 10:00:00')),
                nivelEscalamientoId: $ctx['nivelEscalamientoId'],
            ),
        ));

        $this->assertDatabaseHas('compromisos', [
            'caso_id' => $ctx['casoId'],
            'proyecto_id' => $ctx['proyectoId'],
            'tipo_compromiso' => 'resolucion_ticket',
            'estado' => 'pendiente',
        ]);
        $compromisoId = (int) DB::table('compromisos')->where('caso_id', $ctx['casoId'])->value('id');
        $this->assertDatabaseHas('compromisos_resolucion_ticket', [
            'compromiso_id' => $compromisoId,
            'accion_comprometida' => 'Revisión de infraestructura y respuesta al cliente',
        ]);

        $this->assertTrue((bool) DB::table('casos')->where('id', $ctx['casoId'])->value('tiene_compromiso_vigente'));
    }

    public function test_marcar_resolucion_cumplida(): void
    {
        $ctx = $this->contexto();
        $this->registrarResolucion($ctx);
        $compromisoId = (int) DB::table('compromisos')->where('caso_id', $ctx['casoId'])->value('id');

        $this->app->make(MarcarResolucionCumplida::class)->execute(new ResolverCompromisoInput(
            compromisoId: $compromisoId,
            fechaResolucion: new DateTimeImmutable('2026-04-18 18:00:00'),
        ));

        $this->assertDatabaseHas('compromisos', ['id' => $compromisoId, 'estado' => 'cumplido']);
        $this->assertFalse((bool) DB::table('casos')->where('id', $ctx['casoId'])->value('tiene_compromiso_vigente'));
    }

    public function test_marcar_resolucion_rota(): void
    {
        $ctx = $this->contexto();
        $this->registrarResolucion($ctx);
        $compromisoId = (int) DB::table('compromisos')->where('caso_id', $ctx['casoId'])->value('id');

        $this->app->make(MarcarResolucionRota::class)->execute(new ResolverCompromisoInput(
            compromisoId: $compromisoId,
            fechaResolucion: new DateTimeImmutable('2026-04-19 11:00:00'),
        ));

        $this->assertDatabaseHas('compromisos', ['id' => $compromisoId, 'estado' => 'roto']);
    }

    public function test_cancelar_resolucion(): void
    {
        $ctx = $this->contexto();
        $this->registrarResolucion($ctx);
        $compromisoId = (int) DB::table('compromisos')->where('caso_id', $ctx['casoId'])->value('id');

        $this->app->make(CancelarResolucion::class)->execute(new ResolverCompromisoInput(
            compromisoId: $compromisoId,
            fechaResolucion: new DateTimeImmutable('2026-04-18 16:00:00'),
        ));

        $this->assertDatabaseHas('compromisos', ['id' => $compromisoId, 'estado' => 'cancelado']);
    }

    /**
     * @param  array{proyectoId:int, casoId:int, personaId:int, usuarioId:int, nivelEscalamientoId:int, escalado:array{tipo_gestion_id:int, resultado_id:int, canal_id:int, motivo_no_contacto_id:int, causa_id:int}, compromisoSla:array{tipo_gestion_id:int, resultado_id:int, canal_id:int, motivo_no_contacto_id:int, causa_id:int}}  $ctx
     */
    private function registrarResolucion(array $ctx): void
    {
        $this->app->make(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId: (string) Str::ulid(),
            proyectoId: $ctx['proyectoId'],
            casoId: $ctx['casoId'],
            personaId: $ctx['personaId'],
            contactoId: null,
            canalId: $ctx['compromisoSla']['canal_id'],
            tipoGestionId: $ctx['compromisoSla']['tipo_gestion_id'],
            resultadoId: $ctx['compromisoSla']['resultado_id'],
            motivoNoContactoId: null,
            causaId: null,
            usuarioId: $ctx['usuarioId'],
            notas: null,
            duracion: null,
            creadaEn: new DateTimeImmutable('2026-04-18 10:00:00'),
            datosCompromiso: new DatosResolucionTicket(
                accion: new AccionComprometida('Resolución estándar'),
                fechaLimite: new FechaLimiteSla(new DateTimeImmutable('2026-04-19 10:00:00')),
            ),
        ));
    }

    /**
     * Escenario mínimo de un proyecto CX con un ticket abierto y las dos cascadas
     * de gestión que el test usa: la de escalamiento (exige causa) y la del
     * compromiso de SLA. Ambas exigen compromiso, que es lo que dispara el
     * listener `CrearResolucionDesdeGestion`.
     *
     * @return array{proyectoId:int, casoId:int, personaId:int, usuarioId:int, nivelEscalamientoId:int, escalado:array{tipo_gestion_id:int, resultado_id:int, canal_id:int, motivo_no_contacto_id:int, causa_id:int}, compromisoSla:array{tipo_gestion_id:int, resultado_id:int, canal_id:int, motivo_no_contacto_id:int, causa_id:int}}
     */
    private function contexto(): array
    {
        $proyecto = $this->crearProyectoCx();
        $cartera = $this->crearCarteraEn($proyecto, 'SOPORTE_GENERAL');
        $estado = $this->crearEstadoCasoEn($proyecto, 'ABIERTO');
        $persona = $this->crearPersonaEn($proyecto);
        $usuario = $this->crearGestor($proyecto);

        $escalado = $this->crearCascadaGestionEn($proyecto, [
            'codigo_tipo' => 'LLAMADA_ENTRANTE',
            'codigo_resultado' => 'ESCALADO',
            'requiere_compromiso' => true,
            'requiere_causa' => true,
        ]);

        $compromisoSla = $this->crearCascadaGestionEn($proyecto, [
            'codigo_tipo' => 'LLAMADA_ENTRANTE_SLA',
            'codigo_resultado' => 'COMPROMISO_SLA',
            'requiere_compromiso' => true,
        ]);

        // No hay helper para `niveles_escalamiento` en EscenarioOperativo.
        $nivelEscalamientoId = (int) DB::table('niveles_escalamiento')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'N2',
            'nombre' => 'Nivel 2',
            'nivel' => 2,
            'activo' => true,
            'orden' => 20,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        $out = $this->app->make(RegistrarCasoTicketCx::class)->execute(new RegistrarCasoTicketCxInput(
            proyectoId: (int) $proyecto->id,
            carteraId: (int) $cartera->id,
            personaId: (int) $persona->id,
            estadoCasoId: (int) $estado->id,
            fechaIngreso: new DateTimeImmutable('2026-04-18'),
            prioridad: 100,
            codigoTicket: 'TKT-RES-'.Str::random(4),
            asunto: 'Ticket para resolver',
            descripcion: null,
            categoriaTicketId: null,
            prioridadTicketId: null,
            nivelSlaId: null,
            nivelEscalamientoId: null,
            fechaReporte: new DateTimeImmutable('2026-04-18 09:00:00'),
            fechaLimiteSla: null,
        ));

        return [
            'proyectoId' => (int) $proyecto->id,
            'casoId' => $out->casoId,
            'personaId' => (int) $persona->id,
            'usuarioId' => (int) $usuario->id,
            'nivelEscalamientoId' => $nivelEscalamientoId,
            'escalado' => $escalado,
            'compromisoSla' => $compromisoSla,
        ];
    }
}
