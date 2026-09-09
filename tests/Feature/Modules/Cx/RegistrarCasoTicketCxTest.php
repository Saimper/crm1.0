<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Cx;

use App\Modules\Casos\Domain\Events\CasoCreado;
use App\Modules\Cx\Application\DTOs\RegistrarCasoTicketCxInput;
use App\Modules\Cx\Application\UseCases\RegistrarCasoTicketCx;
use App\Modules\Cx\Domain\Exceptions\CodigoTicketYaRegistrado;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class RegistrarCasoTicketCxTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_registra_ticket_cx_crea_caso_base_y_especializacion(): void
    {
        [$proyecto, $carteraId, $personaId, $estadoId] = $this->contextoCx();
        $proyectoId = (int) $proyecto->id;
        Event::fake([CasoCreado::class]);

        $output = $this->app->make(RegistrarCasoTicketCx::class)->execute(new RegistrarCasoTicketCxInput(
            proyectoId: $proyectoId,
            carteraId: $carteraId,
            personaId: $personaId,
            estadoCasoId: $estadoId,
            fechaIngreso: new DateTimeImmutable('2026-04-18'),
            prioridad: 100,
            codigoTicket: 'TKT-TEST-001',
            asunto: 'Ticket de prueba',
            descripcion: 'Descripción.',
            categoriaTicketId: $this->crearCategoriaTicketEn($proyecto, 'ACCESO'),
            prioridadTicketId: $this->crearPrioridadTicketEn($proyecto, 'ALTA'),
            nivelSlaId: $this->crearNivelSlaEn($proyecto, 'SLA_24H'),
            nivelEscalamientoId: $this->crearNivelEscalamientoEn($proyecto, 'N1'),
            fechaReporte: new DateTimeImmutable('2026-04-18 10:00:00'),
            fechaLimiteSla: new DateTimeImmutable('2026-04-19 10:00:00'),
        ));

        $this->assertDatabaseHas('casos', [
            'id' => $output->casoId,
            'proyecto_id' => $proyectoId,
            'tipo_caso' => 'ticket_cx',
        ]);
        $this->assertDatabaseHas('casos_ticket_cx', [
            'caso_id' => $output->casoId,
            'codigo_ticket' => 'TKT-TEST-001',
            'asunto' => 'Ticket de prueba',
        ]);
        Event::assertDispatched(CasoCreado::class);
    }

    public function test_rechaza_codigo_ticket_duplicado_en_mismo_proyecto(): void
    {
        [$proyecto, $carteraId, $personaId, $estadoId] = $this->contextoCx();
        $proyectoId = (int) $proyecto->id;
        $useCase = $this->app->make(RegistrarCasoTicketCx::class);

        $useCase->execute($this->inputBase($proyectoId, $carteraId, $personaId, $estadoId, 'TKT-DUP'));

        $this->expectException(CodigoTicketYaRegistrado::class);
        $useCase->execute($this->inputBase($proyectoId, $carteraId, $personaId, $estadoId, 'TKT-DUP'));
    }

    public function test_mismo_codigo_ticket_permitido_en_proyectos_distintos(): void
    {
        $mandante = $this->crearMandante();
        [$proyectoCx, $carteraIdCx, $personaIdCx, $estadoCx] = $this->contextoCx($mandante);

        // Segundo proyecto del mismo mandante: el unique de `casos_ticket_cx` es
        // (proyecto_id, codigo_ticket), así que el mismo código no debe colisionar.
        $proyectoCobranza = $this->crearProyecto('cobranza', $mandante, 'COB_PARALELO_2026');
        $carteraCob = $this->crearCarteraEn($proyectoCobranza, 'GENERAL');
        $estadoCob = $this->crearEstadoCasoEn($proyectoCobranza, 'ABIERTO');
        $personaCob = $this->crearPersonaEn($proyectoCobranza);

        $useCase = $this->app->make(RegistrarCasoTicketCx::class);
        $outCx = $useCase->execute($this->inputBase((int) $proyectoCx->id, $carteraIdCx, $personaIdCx, $estadoCx, 'TKT-SHARED'));

        // Insertamos directo porque el otro proyecto es de tipo cobranza pero el índice unique es por proyecto,
        // no por tipo, así que el caso Cx con mismo código en un proyecto cobranza igualmente es válido por schema.
        // (Mantiene la prueba de aislamiento a nivel de unique constraint.)
        DB::table('casos_ticket_cx')->insert([
            'caso_id' => (int) DB::table('casos')->insertGetId([
                'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoCobranza->id,
                'cartera_id' => $carteraCob->id, 'persona_id' => $personaCob->id,
                'tipo_caso' => 'ticket_cx', 'estado_caso_id' => $estadoCob->id,
                'fecha_ingreso' => '2026-04-18', 'prioridad' => 100,
            ]),
            'proyecto_id' => $proyectoCobranza->id,
            'codigo_ticket' => 'TKT-SHARED',
            'asunto' => 'Otro proyecto',
            'fecha_reporte' => '2026-04-18 10:00:00',
        ]);

        $this->assertNotNull($outCx->casoId);
        $this->assertSame(2, DB::table('casos_ticket_cx')->where('codigo_ticket', 'TKT-SHARED')->count());
    }

    /** @return array{stdClass,int,int,int} */
    private function contextoCx(?stdClass $mandante = null): array
    {
        $proyecto = $this->crearProyectoCx($mandante);
        $cartera = $this->crearCarteraEn($proyecto, 'SOPORTE_GENERAL');
        $estado = $this->crearEstadoCasoEn($proyecto, 'ABIERTO');
        $persona = $this->crearPersonaEn($proyecto);

        return [$proyecto, (int) $cartera->id, (int) $persona->id, (int) $estado->id];
    }

    private function inputBase(int $proyectoId, int $carteraId, int $personaId, int $estadoId, string $codigo): RegistrarCasoTicketCxInput
    {
        return new RegistrarCasoTicketCxInput(
            proyectoId: $proyectoId,
            carteraId: $carteraId,
            personaId: $personaId,
            estadoCasoId: $estadoId,
            fechaIngreso: new DateTimeImmutable('2026-04-18'),
            prioridad: 100,
            codigoTicket: $codigo,
            asunto: 'Ticket '.$codigo,
            descripcion: null,
            categoriaTicketId: null,
            prioridadTicketId: null,
            nivelSlaId: null,
            nivelEscalamientoId: null,
            fechaReporte: new DateTimeImmutable('2026-04-18 10:00:00'),
            fechaLimiteSla: null,
        );
    }

    private function crearCategoriaTicketEn(stdClass $proyecto, string $codigo): int
    {
        return (int) DB::table('categorias_ticket')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => $codigo,
            'nombre' => 'Categoría '.$codigo,
            'activo' => true,
            'orden' => 10,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
    }

    private function crearPrioridadTicketEn(stdClass $proyecto, string $codigo): int
    {
        return (int) DB::table('prioridades_ticket')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => $codigo,
            'nombre' => 'Prioridad '.$codigo,
            'peso' => 100,
            'activo' => true,
            'orden' => 10,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
    }

    private function crearNivelSlaEn(stdClass $proyecto, string $codigo): int
    {
        return (int) DB::table('niveles_sla')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => $codigo,
            'nombre' => 'SLA '.$codigo,
            'horas_resolucion' => 24,
            'activo' => true,
            'orden' => 10,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
    }

    private function crearNivelEscalamientoEn(stdClass $proyecto, string $codigo): int
    {
        return (int) DB::table('niveles_escalamiento')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => $codigo,
            'nombre' => 'Nivel '.$codigo,
            'nivel' => 1,
            'activo' => true,
            'orden' => 10,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
    }
}
