<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Cobranza;

use App\Modules\Cobranza\Application\DTOs\RegistrarCasoCobranzaInput;
use App\Modules\Cobranza\Application\UseCases\CancelarPromesa;
use App\Modules\Cobranza\Application\UseCases\MarcarPromesaCumplida;
use App\Modules\Cobranza\Application\UseCases\MarcarPromesaRota;
use App\Modules\Cobranza\Application\UseCases\RegistrarCasoCobranza;
use App\Modules\Cobranza\Domain\ValueObjects\DatosPromesaPago;
use App\Modules\Cobranza\Domain\ValueObjects\FechaPromesa;
use App\Modules\Cobranza\Domain\ValueObjects\MontoPromesa;
use App\Modules\Compromisos\Application\DTOs\ResolverCompromisoInput;
use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\UseCases\RegistrarGestion;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CrearPromesaDesdeGestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

    }

    public function test_registrar_gestion_promesa_pago_crea_compromiso_y_promesa(): void
    {
        $ctx = $this->contexto();

        $this->app->make(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId: (string) Str::ulid(),
            proyectoId: $ctx['proyectoId'],
            casoId: $ctx['casoId'],
            personaId: $ctx['personaId'],
            contactoId: null,
            canalId: $this->idGlobal('canales', 'TELEFONO'),
            tipoGestionId: $this->idProyecto('tipos_gestion', 'LLAMADA_SALIENTE', $ctx['proyectoId']),
            resultadoId: $this->idProyecto('resultados', 'PROMESA_PAGO', $ctx['proyectoId']),
            motivoNoContactoId: null,
            causaId: $this->idProyecto('causas_gestion', 'DESEMPLEO', $ctx['proyectoId']),
            usuarioId: $ctx['usuarioId'],
            notas: 'Promesa registrada.',
            duracion: null,
            creadaEn: new DateTimeImmutable('2026-04-17 10:00:00'),
            datosCompromiso: new DatosPromesaPago(
                monto: new MontoPromesa('1500.00', 'USD'),
                fechaVencimiento: new FechaPromesa(new DateTimeImmutable('2026-04-24')),
                tipoPagoId: $this->idProyecto('tipos_pago', 'TRANSFERENCIA', $ctx['proyectoId']),
            ),
        ));

        $this->assertDatabaseHas('compromisos', [
            'caso_id' => $ctx['casoId'],
            'proyecto_id' => $ctx['proyectoId'],
            'tipo_compromiso' => 'promesa_pago',
            'estado' => 'pendiente',
        ]);
        $compromisoId = (int) DB::table('compromisos')->where('caso_id', $ctx['casoId'])->value('id');
        $this->assertDatabaseHas('compromisos_promesa_pago', [
            'compromiso_id' => $compromisoId,
            'monto' => '1500.00',
            'moneda' => 'USD',
        ]);
        // Listener de Casos (ActivarBanderaCompromisoVigente) activa la bandera al escuchar CompromisoCreado.
        $this->assertTrue((bool) DB::table('casos')->where('id', $ctx['casoId'])->value('tiene_compromiso_vigente'));
    }

    public function test_no_crea_promesa_si_proyecto_no_es_cobranza(): void
    {
        $ctx = $this->contexto();
        DB::table('proyectos')->where('id', $ctx['proyectoId'])->update(['tipo_operacion' => 'cx']);

        $this->app->make(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId: (string) Str::ulid(),
            proyectoId: $ctx['proyectoId'],
            casoId: $ctx['casoId'],
            personaId: $ctx['personaId'],
            contactoId: null,
            canalId: $this->idGlobal('canales', 'TELEFONO'),
            tipoGestionId: $this->idProyecto('tipos_gestion', 'LLAMADA_SALIENTE', $ctx['proyectoId']),
            resultadoId: $this->idProyecto('resultados', 'PROMESA_PAGO', $ctx['proyectoId']),
            motivoNoContactoId: null,
            causaId: $this->idProyecto('causas_gestion', 'DESEMPLEO', $ctx['proyectoId']),
            usuarioId: $ctx['usuarioId'],
            notas: null,
            duracion: null,
            creadaEn: new DateTimeImmutable('2026-04-17 10:00:00'),
            datosCompromiso: new DatosPromesaPago(
                monto: new MontoPromesa('100.00'),
                fechaVencimiento: new FechaPromesa(new DateTimeImmutable('2026-04-24')),
            ),
        ));

        $this->assertSame(0, DB::table('compromisos')->where('caso_id', $ctx['casoId'])->count());
        $this->assertSame(0, DB::table('compromisos_promesa_pago')->count());
    }

    public function test_marcar_promesa_cumplida_actualiza_bandera_vigente(): void
    {
        $ctx = $this->contexto();
        $this->registrarPromesa($ctx);
        $compromisoId = (int) DB::table('compromisos')->where('caso_id', $ctx['casoId'])->value('id');

        $this->app->make(MarcarPromesaCumplida::class)->execute(new ResolverCompromisoInput(
            compromisoId: $compromisoId,
            fechaResolucion: new DateTimeImmutable('2026-04-20'),
        ));

        $this->assertDatabaseHas('compromisos', ['id' => $compromisoId, 'estado' => 'cumplido']);
        $this->assertFalse((bool) DB::table('casos')->where('id', $ctx['casoId'])->value('tiene_compromiso_vigente'));
    }

    public function test_marcar_promesa_rota_actualiza_bandera_vigente(): void
    {
        $ctx = $this->contexto();
        $this->registrarPromesa($ctx);
        $compromisoId = (int) DB::table('compromisos')->where('caso_id', $ctx['casoId'])->value('id');

        $this->app->make(MarcarPromesaRota::class)->execute(new ResolverCompromisoInput(
            compromisoId: $compromisoId,
            fechaResolucion: new DateTimeImmutable('2026-04-25'),
        ));

        $this->assertDatabaseHas('compromisos', ['id' => $compromisoId, 'estado' => 'roto']);
        $this->assertFalse((bool) DB::table('casos')->where('id', $ctx['casoId'])->value('tiene_compromiso_vigente'));
    }

    public function test_cancelar_promesa_actualiza_bandera_vigente(): void
    {
        $ctx = $this->contexto();
        $this->registrarPromesa($ctx);
        $compromisoId = (int) DB::table('compromisos')->where('caso_id', $ctx['casoId'])->value('id');

        $this->app->make(CancelarPromesa::class)->execute(new ResolverCompromisoInput(
            compromisoId: $compromisoId,
            fechaResolucion: new DateTimeImmutable('2026-04-18'),
        ));

        $this->assertDatabaseHas('compromisos', ['id' => $compromisoId, 'estado' => 'cancelado']);
        $this->assertFalse((bool) DB::table('casos')->where('id', $ctx['casoId'])->value('tiene_compromiso_vigente'));
    }

    /** @param array{proyectoId:int, casoId:int, personaId:int, usuarioId:int} $ctx */
    private function registrarPromesa(array $ctx): void
    {
        $this->app->make(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId: (string) Str::ulid(),
            proyectoId: $ctx['proyectoId'],
            casoId: $ctx['casoId'],
            personaId: $ctx['personaId'],
            contactoId: null,
            canalId: $this->idGlobal('canales', 'TELEFONO'),
            tipoGestionId: $this->idProyecto('tipos_gestion', 'LLAMADA_SALIENTE', $ctx['proyectoId']),
            resultadoId: $this->idProyecto('resultados', 'PROMESA_PAGO', $ctx['proyectoId']),
            motivoNoContactoId: null,
            causaId: $this->idProyecto('causas_gestion', 'DESEMPLEO', $ctx['proyectoId']),
            usuarioId: $ctx['usuarioId'],
            notas: null,
            duracion: null,
            creadaEn: new DateTimeImmutable('2026-04-17 10:00:00'),
            datosCompromiso: new DatosPromesaPago(
                monto: new MontoPromesa('800.00'),
                fechaVencimiento: new FechaPromesa(new DateTimeImmutable('2026-04-24')),
            ),
        ));
    }

    /** @return array{proyectoId:int, casoId:int, personaId:int, usuarioId:int} */
    private function contexto(): array
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $carteraId = (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->where('codigo', 'CONSUMO')->value('id');
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $estadoAbiertoId = (int) DB::table('estados_caso')
            ->where('proyecto_id', $proyectoId)->where('codigo', 'ABIERTO')->value('id');

        $usuarioId = (int) DB::table('users')->insertGetId([
            'name' => 'Tester', 'email' => 'tester.'.Str::random(6).'@crm.local',
            'password' => bcrypt('x'), 'activo' => true,
        ]);

        $personaId = (int) DB::table('personas')->insertGetId([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoId,
            'tipo_persona' => 'fisica', 'tipo_identificacion_id' => $tipoCed,
            'identificacion' => (string) random_int(1_000_000_000, 9_999_999_999),
            'nombres' => 'Test', 'apellidos' => 'User',
        ]);

        $output = $this->app->make(RegistrarCasoCobranza::class)->execute(new RegistrarCasoCobranzaInput(
            proyectoId: $proyectoId,
            carteraId: $carteraId,
            personaId: $personaId,
            estadoCasoId: $estadoAbiertoId,
            fechaIngreso: new DateTimeImmutable('2026-04-17'),
            prioridad: 100,
            numeroPrestamo: 'PRST-TEST-'.Str::random(4),
            moneda: 'USD',
            montoOriginal: '5000.00',
            saldoCapital: '4500.00',
            saldoInteres: '100.00',
            saldoTotal: '4600.00',
            cuotaMensual: '420.00',
            cuotasTotales: 12,
            cuotasPagadas: 1,
            diasMora: 20,
            fechaDesembolso: new DateTimeImmutable('2026-01-01'),
            fechaVencimiento: new DateTimeImmutable('2027-01-01'),
        ));

        return [
            'proyectoId' => $proyectoId,
            'casoId' => $output->casoId,
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
