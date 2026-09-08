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
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class CrearPromesaDesdeGestionTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
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
            canalId: $ctx['canalId'],
            tipoGestionId: $ctx['tipoGestionId'],
            resultadoId: $ctx['resultadoId'],
            motivoNoContactoId: null,
            causaId: $ctx['causaId'],
            usuarioId: $ctx['usuarioId'],
            notas: 'Promesa registrada.',
            duracion: null,
            creadaEn: new DateTimeImmutable('2026-04-17 10:00:00'),
            datosCompromiso: new DatosPromesaPago(
                monto: new MontoPromesa('1500.00', 'USD'),
                fechaVencimiento: new FechaPromesa(new DateTimeImmutable('2026-04-24')),
                tipoPagoId: $ctx['tipoPagoId'],
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
            canalId: $ctx['canalId'],
            tipoGestionId: $ctx['tipoGestionId'],
            resultadoId: $ctx['resultadoId'],
            motivoNoContactoId: null,
            causaId: $ctx['causaId'],
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

    /** @param array{proyectoId:int, casoId:int, personaId:int, usuarioId:int, canalId:int, tipoGestionId:int, resultadoId:int, causaId:int, tipoPagoId:int} $ctx */
    private function registrarPromesa(array $ctx): void
    {
        $this->app->make(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId: (string) Str::ulid(),
            proyectoId: $ctx['proyectoId'],
            casoId: $ctx['casoId'],
            personaId: $ctx['personaId'],
            contactoId: null,
            canalId: $ctx['canalId'],
            tipoGestionId: $ctx['tipoGestionId'],
            resultadoId: $ctx['resultadoId'],
            motivoNoContactoId: null,
            causaId: $ctx['causaId'],
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

    /**
     * El escenario que antes venía del seeder demo: proyecto de cobranza con su
     * cartera, persona, estado, gestor y la cascada canal → tipo → resultado con
     * `requiere_compromiso` (que es la bandera que dispara la promesa).
     *
     * @return array{proyectoId:int, casoId:int, personaId:int, usuarioId:int, canalId:int, tipoGestionId:int, resultadoId:int, causaId:int, tipoPagoId:int}
     */
    private function contexto(): array
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto, 'ABIERTO');
        $usuario = $this->crearGestor($proyecto);

        $cascada = $this->crearCascadaGestionEn($proyecto, [
            'requiere_compromiso' => true,
            'requiere_causa' => true,
            'codigo_tipo' => 'LLAMADA_SALIENTE',
            'codigo_resultado' => 'PROMESA_PAGO',
        ]);

        $output = $this->app->make(RegistrarCasoCobranza::class)->execute(new RegistrarCasoCobranzaInput(
            proyectoId: (int) $proyecto->id,
            carteraId: (int) $cartera->id,
            personaId: (int) $persona->id,
            estadoCasoId: (int) $estado->id,
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
            'proyectoId' => (int) $proyecto->id,
            'casoId' => $output->casoId,
            'personaId' => (int) $persona->id,
            'usuarioId' => (int) $usuario->id,
            'canalId' => $cascada['canal_id'],
            'tipoGestionId' => $cascada['tipo_gestion_id'],
            'resultadoId' => $cascada['resultado_id'],
            'causaId' => $cascada['causa_id'],
            'tipoPagoId' => $this->crearTipoPagoEn($proyecto),
        ];
    }
}
