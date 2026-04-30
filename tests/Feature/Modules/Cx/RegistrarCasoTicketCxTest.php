<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Cx;

use App\Modules\Casos\Domain\Events\CasoCreado;
use App\Modules\Cx\Application\DTOs\RegistrarCasoTicketCxInput;
use App\Modules\Cx\Application\UseCases\RegistrarCasoTicketCx;
use App\Modules\Cx\Domain\Exceptions\CodigoTicketYaRegistrado;
use Database\Seeders\Catalogos\TiposIdentificacionSeeder;
use Database\Seeders\Cx\CategoriasTicketDemoSeeder;
use Database\Seeders\Cx\EstadosCasoCxDemoSeeder;
use Database\Seeders\Cx\NivelesEscalamientoDemoSeeder;
use Database\Seeders\Cx\NivelesSlaDemoSeeder;
use Database\Seeders\Cx\PrioridadesTicketDemoSeeder;
use Database\Seeders\Tenancy\CarterasDemoSeeder;
use Database\Seeders\Tenancy\MandantesDemoSeeder;
use Database\Seeders\Tenancy\ProyectosDemoSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RegistrarCasoTicketCxTest extends TestCase
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
            CategoriasTicketDemoSeeder::class,
            PrioridadesTicketDemoSeeder::class,
            NivelesSlaDemoSeeder::class,
            NivelesEscalamientoDemoSeeder::class,
        ]);
    }

    public function test_registra_ticket_cx_crea_caso_base_y_especializacion(): void
    {
        [$proyectoId, $carteraId, $personaId, $estadoId] = $this->contextoCx();
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
            categoriaTicketId: $this->idProyecto('categorias_ticket', 'ACCESO', $proyectoId),
            prioridadTicketId: $this->idProyecto('prioridades_ticket', 'ALTA', $proyectoId),
            nivelSlaId: $this->idProyecto('niveles_sla', 'SLA_24H', $proyectoId),
            nivelEscalamientoId: $this->idProyecto('niveles_escalamiento', 'N1', $proyectoId),
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
        [$proyectoId, $carteraId, $personaId, $estadoId] = $this->contextoCx();
        $useCase = $this->app->make(RegistrarCasoTicketCx::class);

        $useCase->execute($this->inputBase($proyectoId, $carteraId, $personaId, $estadoId, 'TKT-DUP'));

        $this->expectException(CodigoTicketYaRegistrado::class);
        $useCase->execute($this->inputBase($proyectoId, $carteraId, $personaId, $estadoId, 'TKT-DUP'));
    }

    public function test_mismo_codigo_ticket_permitido_en_proyectos_distintos(): void
    {
        [$proyectoIdCx, $carteraIdCx, $personaIdCx, $estadoCx] = $this->contextoCx();

        $proyectoCobranzaId = $this->crearProyectoCobranzaParalelo();
        $carteraCob = (int) DB::table('carteras')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoCobranzaId,
            'codigo' => 'GENERAL',
            'nombre' => 'General',
            'activo' => true,
        ]);
        $estadoCob = (int) DB::table('estados_caso')->insertGetId([
            'proyecto_id' => $proyectoCobranzaId,
            'codigo' => 'ABIERTO',
            'nombre' => 'Abierto',
            'activo' => true,
            'orden' => 10,
        ]);
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $personaCob = (int) DB::table('personas')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoCobranzaId,
            'tipo_persona' => 'fisica',
            'tipo_identificacion_id' => $tipoCed,
            'identificacion' => (string) random_int(1_000_000_000, 9_999_999_999),
            'nombres' => 'Persona Cob',
        ]);

        $useCase = $this->app->make(RegistrarCasoTicketCx::class);
        $outCx = $useCase->execute($this->inputBase($proyectoIdCx, $carteraIdCx, $personaIdCx, $estadoCx, 'TKT-SHARED'));

        // Mismo código TKT-SHARED en otro proyecto (del mismo mandante) no colisiona.
        // Insertamos directo porque el otro proyecto es de tipo cobranza pero el índice unique es por proyecto,
        // no por tipo, así que el caso Cx con mismo código en un proyecto cobranza igualmente es válido por schema.
        // (Mantiene la prueba de aislamiento a nivel de unique constraint.)
        DB::table('casos_ticket_cx')->insert([
            'caso_id' => (int) DB::table('casos')->insertGetId([
                'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoCobranzaId,
                'cartera_id' => $carteraCob, 'persona_id' => $personaCob,
                'tipo_caso' => 'ticket_cx', 'estado_caso_id' => $estadoCob,
                'fecha_ingreso' => '2026-04-18', 'prioridad' => 100,
            ]),
            'proyecto_id' => $proyectoCobranzaId,
            'codigo_ticket' => 'TKT-SHARED',
            'asunto' => 'Otro proyecto',
            'fecha_reporte' => '2026-04-18 10:00:00',
        ]);

        $this->assertNotNull($outCx->casoId);
        $this->assertSame(2, DB::table('casos_ticket_cx')->where('codigo_ticket', 'TKT-SHARED')->count());
    }

    /** @return array{int,int,int,int} */
    private function contextoCx(): array
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
        $carteraId = (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->where('codigo', 'SOPORTE_GENERAL')->value('id');
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $estadoId = (int) DB::table('estados_caso')->where('proyecto_id', $proyectoId)->where('codigo', 'ABIERTO')->value('id');

        $personaId = (int) DB::table('personas')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'tipo_persona' => 'fisica',
            'tipo_identificacion_id' => $tipoCed,
            'identificacion' => (string) random_int(1_000_000_000, 9_999_999_999),
            'nombres' => 'Tester',
            'apellidos' => 'CX',
        ]);

        return [$proyectoId, $carteraId, $personaId, $estadoId];
    }

    private function crearProyectoCobranzaParalelo(): int
    {
        $mandanteId = (int) DB::table('mandantes')->where('codigo', 'BPO_DEMO')->value('id');

        return (int) DB::table('proyectos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'mandante_id' => $mandanteId,
            'codigo' => 'COB_PARALELO_2026',
            'nombre' => 'Cobranza paralela',
            'tipo_operacion' => 'cobranza',
            'activo' => true,
            'fecha_inicio' => '2026-04-18',
        ]);
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

    private function idProyecto(string $tabla, string $codigo, int $proyectoId): int
    {
        return (int) DB::table($tabla)->where('proyecto_id', $proyectoId)->where('codigo', $codigo)->value('id');
    }
}
