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
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CrearResolucionDesdeGestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

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
            canalId: $this->idGlobal('canales', 'TELEFONO'),
            tipoGestionId: $this->idProyecto('tipos_gestion', 'LLAMADA_ENTRANTE', $ctx['proyectoId']),
            resultadoId: $this->idProyecto('resultados', 'ESCALADO', $ctx['proyectoId']),
            motivoNoContactoId: null,
            causaId: $this->idProyecto('causas_gestion', 'CAIDO', $ctx['proyectoId']),
            usuarioId: $ctx['usuarioId'],
            notas: 'Cliente reporta caída general, se escala a nivel 2.',
            duracion: null,
            creadaEn: new DateTimeImmutable('2026-04-18 10:00:00'),
            datosCompromiso: new DatosResolucionTicket(
                accion: new AccionComprometida('Revisión de infraestructura y respuesta al cliente'),
                fechaLimite: new FechaLimiteSla(new DateTimeImmutable('2026-04-19 10:00:00')),
                nivelEscalamientoId: $this->idProyecto('niveles_escalamiento', 'N2', $ctx['proyectoId']),
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

    /** @param array{proyectoId:int, casoId:int, personaId:int, usuarioId:int} $ctx */
    private function registrarResolucion(array $ctx): void
    {
        $this->app->make(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId: (string) Str::ulid(),
            proyectoId: $ctx['proyectoId'],
            casoId: $ctx['casoId'],
            personaId: $ctx['personaId'],
            contactoId: null,
            canalId: $this->idGlobal('canales', 'TELEFONO'),
            tipoGestionId: $this->idProyecto('tipos_gestion', 'LLAMADA_ENTRANTE', $ctx['proyectoId']),
            resultadoId: $this->idProyecto('resultados', 'COMPROMISO_SLA', $ctx['proyectoId']),
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

    /** @return array{proyectoId:int, casoId:int, personaId:int, usuarioId:int} */
    private function contexto(): array
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
        $carteraId = (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->where('codigo', 'SOPORTE_GENERAL')->value('id');
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $estadoId = (int) DB::table('estados_caso')->where('proyecto_id', $proyectoId)->where('codigo', 'ABIERTO')->value('id');

        $usuarioId = (int) DB::table('users')->insertGetId([
            'name' => 'UC', 'email' => 'uc.'.Str::random(6).'@crm.local',
            'password' => bcrypt('x'), 'activo' => true,
        ]);
        $personaId = (int) DB::table('personas')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'tipo_persona' => 'fisica',
            'tipo_identificacion_id' => $tipoCed,
            'identificacion' => (string) random_int(1_000_000_000, 9_999_999_999),
            'nombres' => 'Tester',
            'apellidos' => 'CX',
        ]);

        $out = $this->app->make(RegistrarCasoTicketCx::class)->execute(new RegistrarCasoTicketCxInput(
            proyectoId: $proyectoId,
            carteraId: $carteraId,
            personaId: $personaId,
            estadoCasoId: $estadoId,
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
            'proyectoId' => $proyectoId,
            'casoId' => $out->casoId,
            'personaId' => $personaId,
            'usuarioId' => $usuarioId,
        ];
    }

    private function idGlobal(string $tabla, string $codigo): int
    {
        return (int) DB::table($tabla)->where('codigo', $codigo)->value('id');
    }

    private function idProyecto(string $tabla, string $codigo, int $proyectoId): int
    {
        return (int) DB::table($tabla)->where('proyecto_id', $proyectoId)->where('codigo', $codigo)->value('id');
    }
}
