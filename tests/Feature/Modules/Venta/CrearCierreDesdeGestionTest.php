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
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class CrearCierreDesdeGestionTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
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
            canalId: $ctx['canalId'],
            tipoGestionId: $ctx['tipoGestionId'],
            resultadoId: $ctx['resultadoId'],
            motivoNoContactoId: null,
            causaId: null,
            usuarioId: $ctx['usuarioId'],
            notas: 'Cliente confirma interés en el cierre.',
            duracion: null,
            creadaEn: new DateTimeImmutable('2026-04-18 10:00:00'),
            datosCompromiso: new DatosCierreVenta(
                monto: new MontoCierre('2500.00'),
                fechaEstimada: new FechaCierreEstimada(new DateTimeImmutable('2026-05-10')),
                etapaEmbudoId: $ctx['etapaEmbudoId'],
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

    /** @param array<string, int> $ctx */
    private function registrarCierre(array $ctx): void
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

    /**
     * Escenario mínimo de venta: proyecto, cartera, persona, estado, gestor y la
     * cascada canal → tipo → resultado con `requiere_compromiso`, que es la que
     * dispara el listener CrearCierreDesdeGestion.
     *
     * @return array<string, int>
     */
    private function contexto(): array
    {
        $proyecto = $this->crearProyectoVenta();
        $cartera = $this->crearCarteraEn($proyecto, 'PREMIUM');
        $persona = $this->crearPersonaEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto, 'NUEVO');
        $usuario = $this->crearGestor($proyecto);

        $cascada = $this->crearCascadaGestionEn($proyecto, [
            'requiere_compromiso' => true,
            'codigo_tipo' => 'LLAMADA_SALIENTE',
            'codigo_resultado' => 'PROMESA_CIERRE',
        ]);

        $etapaEmbudoId = $this->crearEtapaEmbudoEn((int) $proyecto->id, 'CIERRE');

        $out = $this->app->make(RegistrarCasoLeadVenta::class)->execute(new RegistrarCasoLeadVentaInput(
            proyectoId: (int) $proyecto->id,
            carteraId: (int) $cartera->id,
            personaId: (int) $persona->id,
            estadoCasoId: (int) $estado->id,
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
            'proyectoId' => (int) $proyecto->id,
            'casoId' => $out->casoId,
            'personaId' => (int) $persona->id,
            'usuarioId' => (int) $usuario->id,
            'canalId' => $cascada['canal_id'],
            'tipoGestionId' => $cascada['tipo_gestion_id'],
            'resultadoId' => $cascada['resultado_id'],
            'etapaEmbudoId' => $etapaEmbudoId,
        ];
    }

    /** `etapas_embudo` no tiene helper en EscenarioOperativo; se inserta aquí. */
    private function crearEtapaEmbudoEn(int $proyectoId, string $codigo): int
    {
        $ahora = Carbon::now();

        return (int) DB::table('etapas_embudo')->insertGetId([
            'proyecto_id' => $proyectoId,
            'codigo' => $codigo,
            'nombre' => 'Etapa '.$codigo,
            'nivel' => 1,
            'probabilidad_cierre' => 80,
            'activo' => true,
            'orden' => 10,
            'creada_en' => $ahora,
            'actualizada_en' => $ahora,
        ]);
    }
}
