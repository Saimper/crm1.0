<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Venta;

use App\Modules\Compromisos\Application\DTOs\ResolverCompromisoInput;
use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\UseCases\RegistrarGestion;
use App\Modules\Venta\Application\DTOs\RegistrarCasoLeadVentaInput;
use App\Modules\Venta\Application\UseCases\CancelarCierre;
use App\Modules\Venta\Application\UseCases\MarcarCierreGanado;
use App\Modules\Venta\Application\UseCases\MarcarCierrePerdido;
use App\Modules\Venta\Application\UseCases\RegistrarCasoLeadVenta;
use App\Modules\Venta\Domain\ValueObjects\DatosCierreVenta;
use App\Modules\Venta\Domain\ValueObjects\FechaCierreEstimada;
use App\Modules\Venta\Domain\ValueObjects\MontoCierre;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CrearCierreDesdeGestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

    }

    public function test_registrar_gestion_con_promesa_cierre_crea_compromiso_y_cierre(): void
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
            resultadoId: $this->idProyecto('resultados', 'PROMESA_CIERRE', $ctx['proyectoId']),
            motivoNoContactoId: null,
            causaId: null,
            usuarioId: $ctx['usuarioId'],
            notas: 'Cliente confirma interés en el cierre.',
            duracion: null,
            creadaEn: new DateTimeImmutable('2026-04-18 10:00:00'),
            datosCompromiso: new DatosCierreVenta(
                monto: new MontoCierre('2500.00'),
                fechaEstimada: new FechaCierreEstimada(new DateTimeImmutable('2026-05-10')),
                etapaEmbudoId: $this->idProyecto('etapas_embudo', 'CIERRE', $ctx['proyectoId']),
            ),
        ));

        $this->assertDatabaseHas('compromisos', [
            'caso_id' => $ctx['casoId'],
            'tipo_compromiso' => 'cierre_venta',
            'estado' => 'pendiente',
        ]);
        $compromisoId = (int) DB::table('compromisos')->where('caso_id', $ctx['casoId'])->value('id');
        $this->assertDatabaseHas('compromisos_cierre_venta', [
            'compromiso_id' => $compromisoId,
            'monto_cierre' => '2500.00',
        ]);
        $this->assertTrue((bool) DB::table('casos')->where('id', $ctx['casoId'])->value('tiene_compromiso_vigente'));
    }

    public function test_marcar_cierre_ganado(): void
    {
        $ctx = $this->contexto();
        $this->registrarCierre($ctx);
        $compromisoId = (int) DB::table('compromisos')->where('caso_id', $ctx['casoId'])->value('id');

        $this->app->make(MarcarCierreGanado::class)->execute(new ResolverCompromisoInput(
            compromisoId: $compromisoId,
            fechaResolucion: new DateTimeImmutable('2026-05-01'),
        ));

        $this->assertDatabaseHas('compromisos', ['id' => $compromisoId, 'estado' => 'cumplido']);
        $this->assertFalse((bool) DB::table('casos')->where('id', $ctx['casoId'])->value('tiene_compromiso_vigente'));
    }

    public function test_marcar_cierre_perdido(): void
    {
        $ctx = $this->contexto();
        $this->registrarCierre($ctx);
        $compromisoId = (int) DB::table('compromisos')->where('caso_id', $ctx['casoId'])->value('id');

        $this->app->make(MarcarCierrePerdido::class)->execute(new ResolverCompromisoInput(
            compromisoId: $compromisoId,
            fechaResolucion: new DateTimeImmutable('2026-05-12'),
        ));

        $this->assertDatabaseHas('compromisos', ['id' => $compromisoId, 'estado' => 'roto']);
    }

    public function test_cancelar_cierre(): void
    {
        $ctx = $this->contexto();
        $this->registrarCierre($ctx);
        $compromisoId = (int) DB::table('compromisos')->where('caso_id', $ctx['casoId'])->value('id');

        $this->app->make(CancelarCierre::class)->execute(new ResolverCompromisoInput(
            compromisoId: $compromisoId,
            fechaResolucion: new DateTimeImmutable('2026-04-25'),
        ));

        $this->assertDatabaseHas('compromisos', ['id' => $compromisoId, 'estado' => 'cancelado']);
    }

    /** @param array{proyectoId:int, casoId:int, personaId:int, usuarioId:int} $ctx */
    private function registrarCierre(array $ctx): void
    {
        $this->app->make(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId: (string) Str::ulid(),
            proyectoId: $ctx['proyectoId'],
            casoId: $ctx['casoId'],
            personaId: $ctx['personaId'],
            contactoId: null,
            canalId: $this->idGlobal('canales', 'TELEFONO'),
            tipoGestionId: $this->idProyecto('tipos_gestion', 'LLAMADA_SALIENTE', $ctx['proyectoId']),
            resultadoId: $this->idProyecto('resultados', 'PROMESA_CIERRE', $ctx['proyectoId']),
            motivoNoContactoId: null,
            causaId: null,
            usuarioId: $ctx['usuarioId'],
            notas: null,
            duracion: null,
            creadaEn: new DateTimeImmutable('2026-04-18 10:00:00'),
            datosCompromiso: new DatosCierreVenta(
                monto: new MontoCierre('1000.00'),
                fechaEstimada: new FechaCierreEstimada(new DateTimeImmutable('2026-05-10')),
            ),
        ));
    }

    /** @return array{proyectoId:int, casoId:int, personaId:int, usuarioId:int} */
    private function contexto(): array
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'VENTA_DEMO_2026')->value('id');
        $carteraId = (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->where('codigo', 'PREMIUM')->value('id');
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $estadoId = (int) DB::table('estados_caso')->where('proyecto_id', $proyectoId)->where('codigo', 'NUEVO')->value('id');

        $usuarioId = (int) DB::table('users')->insertGetId([
            'name' => 'UC', 'email' => 'uc.'.Str::random(6).'@crm.local',
            'password' => bcrypt('x'), 'activo' => true,
        ]);
        $personaId = (int) DB::table('personas')->insertGetId([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoId,
            'tipo_persona' => 'fisica', 'tipo_identificacion_id' => $tipoCed,
            'identificacion' => (string) random_int(1_000_000_000, 9_999_999_999),
            'nombres' => 'Tester', 'apellidos' => 'Venta',
        ]);

        $out = $this->app->make(RegistrarCasoLeadVenta::class)->execute(new RegistrarCasoLeadVentaInput(
            proyectoId: $proyectoId,
            carteraId: $carteraId,
            personaId: $personaId,
            estadoCasoId: $estadoId,
            fechaIngreso: new DateTimeImmutable('2026-04-18'),
            prioridad: 100,
            codigoLead: 'LEAD-CIERRE-'.Str::random(4),
            productoVentaId: null,
            etapaEmbudoId: null,
            valorEstimadoMonto: '1500.00',
            moneda: 'USD',
            origenLead: null,
            fechaPrimerContacto: new DateTimeImmutable('2026-04-18'),
            fechaEstimadaCierre: null,
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
