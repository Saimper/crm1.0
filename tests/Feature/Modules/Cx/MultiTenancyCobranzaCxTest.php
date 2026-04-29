<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Cx;

use App\Modules\Cobranza\Application\DTOs\RegistrarCasoCobranzaInput;
use App\Modules\Cobranza\Application\UseCases\RegistrarCasoCobranza;
use App\Modules\Cobranza\Domain\ValueObjects\DatosPromesaPago;
use App\Modules\Cobranza\Domain\ValueObjects\FechaPromesa;
use App\Modules\Cobranza\Domain\ValueObjects\MontoPromesa;
use App\Modules\Cx\Application\DTOs\RegistrarCasoTicketCxInput;
use App\Modules\Cx\Application\UseCases\RegistrarCasoTicketCx;
use App\Modules\Cx\Domain\ValueObjects\AccionComprometida;
use App\Modules\Cx\Domain\ValueObjects\DatosResolucionTicket;
use App\Modules\Cx\Domain\ValueObjects\FechaLimiteSla;
use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\UseCases\RegistrarGestion;
use Database\Seeders\Casos\EstadosCasoDemoSeeder;
use Database\Seeders\Catalogos\TiposIdentificacionSeeder;
use Database\Seeders\Cobranza\CausasMoraDemoSeeder;
use Database\Seeders\Cobranza\TiposPagoDemoSeeder;
use Database\Seeders\Cobranza\TramosMoraDemoSeeder;
use Database\Seeders\Cx\CategoriasTicketDemoSeeder;
use Database\Seeders\Cx\EstadosCasoCxDemoSeeder;
use Database\Seeders\Cx\GestionesCatalogosCxDemoSeeder;
use Database\Seeders\Cx\NivelesEscalamientoDemoSeeder;
use Database\Seeders\Cx\NivelesSlaDemoSeeder;
use Database\Seeders\Cx\PrioridadesTicketDemoSeeder;
use Database\Seeders\Gestiones\CanalesSeeder;
use Database\Seeders\Gestiones\GestionesCatalogosDemoSeeder;
use Database\Seeders\Tenancy\CarterasDemoSeeder;
use Database\Seeders\Tenancy\MandantesDemoSeeder;
use Database\Seeders\Tenancy\ProyectosDemoSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Garantiza aislamiento entre proyectos de tipos distintos del mismo mandante.
 * Dos proyectos (COBRANZA_DEMO_2026 y SOPORTE_DEMO_2026) comparten mandante BPO_DEMO.
 */
final class MultiTenancyCobranzaCxTest extends TestCase
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
            EstadosCasoDemoSeeder::class,
            EstadosCasoCxDemoSeeder::class,
            CanalesSeeder::class,
            GestionesCatalogosDemoSeeder::class,
            GestionesCatalogosCxDemoSeeder::class,
            TramosMoraDemoSeeder::class,
            TiposPagoDemoSeeder::class,
            CausasMoraDemoSeeder::class,
            CategoriasTicketDemoSeeder::class,
            PrioridadesTicketDemoSeeder::class,
            NivelesSlaDemoSeeder::class,
            NivelesEscalamientoDemoSeeder::class,
        ]);
    }

    public function test_listener_cobranza_no_crea_promesa_en_proyecto_cx(): void
    {
        // Contexto: proyecto CX con un ticket, se registra una gestión de compromiso CX.
        // El listener de Cobranza debe ignorar el evento (tipo_operacion != cobranza).
        [$ticketCasoId, $personaCxId, $proyectoCxId, $usuarioId] = $this->contextoCx();

        $this->app->make(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId:          (string) Str::ulid(),
            proyectoId:        $proyectoCxId,
            casoId:            $ticketCasoId,
            personaId:         $personaCxId,
            contactoId:        null,
            canalId:           (int) DB::table('canales')->where('codigo', 'TELEFONO')->value('id'),
            tipoGestionId:     (int) DB::table('tipos_gestion')->where('proyecto_id', $proyectoCxId)->where('codigo', 'LLAMADA_ENTRANTE')->value('id'),
            resultadoId:       (int) DB::table('resultados')->where('proyecto_id', $proyectoCxId)->where('codigo', 'COMPROMISO_SLA')->value('id'),
            motivoNoContactoId: null,
            causaId:           null,
            usuarioId:         $usuarioId,
            notas:             null,
            duracion:          null,
            creadaEn:          new DateTimeImmutable('2026-04-18 10:00:00'),
            datosCompromiso:   new DatosResolucionTicket(
                accion:      new AccionComprometida('Acción CX'),
                fechaLimite: new FechaLimiteSla(new DateTimeImmutable('2026-04-19 10:00:00')),
            ),
        ));

        // Solo se creó compromiso tipo resolucion_ticket (CX), ninguno tipo promesa_pago (cobranza).
        $this->assertSame(1, DB::table('compromisos')->where('tipo_compromiso', 'resolucion_ticket')->count());
        $this->assertSame(0, DB::table('compromisos')->where('tipo_compromiso', 'promesa_pago')->count());
        $this->assertSame(0, DB::table('compromisos_promesa_pago')->count());
    }

    public function test_listener_cx_no_crea_resolucion_en_proyecto_cobranza(): void
    {
        // Contexto: proyecto cobranza con caso + gestión con DatosPromesaPago.
        [$casoCobId, $personaCobId, $proyectoCobId, $usuarioId] = $this->contextoCobranza();

        $this->app->make(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId:          (string) Str::ulid(),
            proyectoId:        $proyectoCobId,
            casoId:            $casoCobId,
            personaId:         $personaCobId,
            contactoId:        null,
            canalId:           (int) DB::table('canales')->where('codigo', 'TELEFONO')->value('id'),
            tipoGestionId:     (int) DB::table('tipos_gestion')->where('proyecto_id', $proyectoCobId)->where('codigo', 'LLAMADA_SALIENTE')->value('id'),
            resultadoId:       (int) DB::table('resultados')->where('proyecto_id', $proyectoCobId)->where('codigo', 'PROMESA_PAGO')->value('id'),
            motivoNoContactoId: null,
            causaId:           (int) DB::table('causas_gestion')->where('proyecto_id', $proyectoCobId)->where('codigo', 'DESEMPLEO')->value('id'),
            usuarioId:         $usuarioId,
            notas:             null,
            duracion:          null,
            creadaEn:          new DateTimeImmutable('2026-04-18 10:00:00'),
            datosCompromiso:   new DatosPromesaPago(
                monto:            new MontoPromesa('500.00'),
                fechaVencimiento: new FechaPromesa(new DateTimeImmutable('2026-04-25')),
            ),
        ));

        // Solo se creó compromiso tipo promesa_pago, ninguno tipo resolucion_ticket.
        $this->assertSame(1, DB::table('compromisos')->where('tipo_compromiso', 'promesa_pago')->count());
        $this->assertSame(0, DB::table('compromisos')->where('tipo_compromiso', 'resolucion_ticket')->count());
        $this->assertSame(0, DB::table('compromisos_resolucion_ticket')->count());
    }

    public function test_persona_con_misma_cedula_aislada_entre_proyectos(): void
    {
        // §2.1 CLAUDE.md: misma identificación en dos proyectos no se ve desde el otro.
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $proyectoCobId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $proyectoCxId  = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');

        $cedula = '1102030405';
        DB::table('personas')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoCobId,
            'tipo_persona' => 'fisica', 'tipo_identificacion_id' => $tipoCed,
            'identificacion' => $cedula, 'nombres' => 'Juan', 'apellidos' => 'Cobranza',
        ]);
        DB::table('personas')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoCxId,
            'tipo_persona' => 'fisica', 'tipo_identificacion_id' => $tipoCed,
            'identificacion' => $cedula, 'nombres' => 'Juan', 'apellidos' => 'CX',
        ]);

        // Desde el scope de cobranza solo vemos una.
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoCobId));
        $countCob = \App\Modules\Personas\Infrastructure\Persistence\Models\PersonaModel::query()
            ->where('identificacion', $cedula)->count();
        $this->assertSame(1, $countCob);

        // Desde el scope CX, también solo una (y distinta).
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoCxId));
        $countCx = \App\Modules\Personas\Infrastructure\Persistence\Models\PersonaModel::query()
            ->where('identificacion', $cedula)->count();
        $this->assertSame(1, $countCx);

        // Pero sin scope (admin), se ven las 2.
        $totalSinScope = \App\Modules\Personas\Infrastructure\Persistence\Models\PersonaModel::query()
            ->sinScopeProyecto()
            ->where('identificacion', $cedula)->count();
        $this->assertSame(2, $totalSinScope);
    }

    /** @return array{int,int,int,int} */
    private function contextoCobranza(): array
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $carteraId  = (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->where('codigo', 'CONSUMO')->value('id');
        $tipoCed    = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $estado     = (int) DB::table('estados_caso')->where('proyecto_id', $proyectoId)->where('codigo', 'ABIERTO')->value('id');

        $usuarioId = (int) DB::table('users')->insertGetId([
            'name' => 'UC', 'email' => 'uc.'.Str::random(6).'@crm.local',
            'password' => bcrypt('x'), 'activo' => true,
        ]);
        $personaId = (int) DB::table('personas')->insertGetId([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoId,
            'tipo_persona' => 'fisica', 'tipo_identificacion_id' => $tipoCed,
            'identificacion' => (string) random_int(1_000_000_000, 9_999_999_999),
            'nombres' => 'Juan', 'apellidos' => 'Cob',
        ]);

        $out = $this->app->make(RegistrarCasoCobranza::class)->execute(new RegistrarCasoCobranzaInput(
            proyectoId:       $proyectoId,
            carteraId:        $carteraId,
            personaId:        $personaId,
            estadoCasoId:     $estado,
            fechaIngreso:     new DateTimeImmutable('2026-04-18'),
            prioridad:        100,
            numeroPrestamo:   'PRST-CROSS-'.Str::random(4),
            moneda:           'USD',
            montoOriginal:    '1000.00',
            saldoCapital:     '900.00',
            saldoInteres:     '10.00',
            saldoTotal:       '910.00',
            cuotaMensual:     '100.00',
            cuotasTotales:    10,
            cuotasPagadas:    1,
            diasMora:         15,
            fechaDesembolso:  new DateTimeImmutable('2026-02-01'),
            fechaVencimiento: new DateTimeImmutable('2026-12-01'),
        ));

        return [$out->casoId, $personaId, $proyectoId, $usuarioId];
    }

    /** @return array{int,int,int,int} */
    private function contextoCx(): array
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
        $carteraId  = (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->where('codigo', 'SOPORTE_GENERAL')->value('id');
        $tipoCed    = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $estado     = (int) DB::table('estados_caso')->where('proyecto_id', $proyectoId)->where('codigo', 'ABIERTO')->value('id');

        $usuarioId = (int) DB::table('users')->insertGetId([
            'name' => 'UC', 'email' => 'uc.'.Str::random(6).'@crm.local',
            'password' => bcrypt('x'), 'activo' => true,
        ]);
        $personaId = (int) DB::table('personas')->insertGetId([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoId,
            'tipo_persona' => 'fisica', 'tipo_identificacion_id' => $tipoCed,
            'identificacion' => (string) random_int(1_000_000_000, 9_999_999_999),
            'nombres' => 'Juan', 'apellidos' => 'Cx',
        ]);

        $out = $this->app->make(RegistrarCasoTicketCx::class)->execute(new RegistrarCasoTicketCxInput(
            proyectoId:          $proyectoId,
            carteraId:           $carteraId,
            personaId:           $personaId,
            estadoCasoId:        $estado,
            fechaIngreso:        new DateTimeImmutable('2026-04-18'),
            prioridad:           100,
            codigoTicket:        'TKT-CROSS-'.Str::random(4),
            asunto:              'Cross-tenant test',
            descripcion:         null,
            categoriaTicketId:   null,
            prioridadTicketId:   null,
            nivelSlaId:          null,
            nivelEscalamientoId: null,
            fechaReporte:        new DateTimeImmutable('2026-04-18 09:00:00'),
            fechaLimiteSla:      null,
        ));

        return [$out->casoId, $personaId, $proyectoId, $usuarioId];
    }
}
