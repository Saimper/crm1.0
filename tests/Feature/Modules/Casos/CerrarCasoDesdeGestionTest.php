<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Casos;

use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\UseCases\RegistrarGestion;
use App\Modules\Gestiones\Domain\ValueObjects\DuracionSegundos;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * El caso se cierra registrando una gestión cuyo resultado lo da por terminado.
 *
 * Antes de esto, `CerrarCaso`, `Caso::cerrar()` y el evento `CasoCerrado`
 * existían pero nadie los invocaba: no había ninguna ruta, componente ni
 * listener que los llamara, y `resultados` no tenía columna que dijera que un
 * resultado cierra el caso. En producción eso dejó los 4.731 casos del
 * proyecto de cobranza en su estado inicial desde el primer día.
 */
final class CerrarCasoDesdeGestionTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** @return array{proyecto: stdClass, casoId: int, personaId: int, usuarioId: int, tipoGestionId: int} */
    private function escenario(): array
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $cartera = $this->crearCarteraEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);
        $abierto = $this->crearEstadoCasoEn($proyecto, 'ABIERTO');
        $usuario = $this->crearGestor($proyecto);

        $casoId = (int) DB::table('casos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'cartera_id' => $cartera->id,
            'persona_id' => $persona->id,
            'tipo_caso' => 'cobranza',
            'estado_caso_id' => $abierto->id,
            'fecha_ingreso' => '2026-09-01',
        ]);

        $tipoGestionId = (int) DB::table('tipos_gestion')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'LLAMADA',
            'nombre' => 'Llamada',
        ]);

        return [
            'proyecto' => $proyecto,
            'casoId' => $casoId,
            'personaId' => (int) $persona->id,
            'usuarioId' => (int) $usuario->id,
            'tipoGestionId' => $tipoGestionId,
        ];
    }

    private function crearResultado(stdClass $proyecto, string $codigo, ?int $estadoCierreId): int
    {
        return (int) DB::table('resultados')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => $codigo,
            'nombre' => $codigo,
            'estado_caso_cierre_id' => $estadoCierreId,
        ]);
    }

    private function registrarGestion(array $ctx, int $resultadoId): void
    {
        $this->app->make(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId: (string) Str::ulid(),
            proyectoId: (int) $ctx['proyecto']->id,
            casoId: $ctx['casoId'],
            personaId: $ctx['personaId'],
            contactoId: null,
            canalId: (int) DB::table('canales')->value('id'),
            tipoGestionId: $ctx['tipoGestionId'],
            resultadoId: $resultadoId,
            motivoNoContactoId: null,
            causaId: null,
            usuarioId: $ctx['usuarioId'],
            notas: 'Gestión de prueba.',
            duracion: new DuracionSegundos(60),
            creadaEn: new DateTimeImmutable('2026-09-07 10:00:00'),
        ));
    }

    public function test_un_resultado_terminal_cierra_el_caso(): void
    {
        $ctx = $this->escenario();
        $finalizado = $this->crearEstadoCasoEn($ctx['proyecto'], 'FINALIZADO', true);
        $resultado = $this->crearResultado($ctx['proyecto'], 'PAGO_TOTAL', (int) $finalizado->id);

        $this->registrarGestion($ctx, $resultado);

        $caso = DB::table('casos')->where('id', $ctx['casoId'])->first();
        $this->assertSame((int) $finalizado->id, (int) $caso->estado_caso_id, 'El caso debió pasar al estado terminal.');
        $this->assertNotNull($caso->cerrado_en, 'El caso debió quedar marcado como cerrado.');
    }

    public function test_un_resultado_normal_deja_el_caso_abierto(): void
    {
        $ctx = $this->escenario();
        $this->crearEstadoCasoEn($ctx['proyecto'], 'FINALIZADO', true);
        $resultado = $this->crearResultado($ctx['proyecto'], 'NO_CONTESTA', null);

        $estadoAntes = (int) DB::table('casos')->where('id', $ctx['casoId'])->value('estado_caso_id');

        $this->registrarGestion($ctx, $resultado);

        $caso = DB::table('casos')->where('id', $ctx['casoId'])->first();
        $this->assertSame($estadoAntes, (int) $caso->estado_caso_id, 'Un resultado sin estado de cierre no cambia el estado.');
        $this->assertNull($caso->cerrado_en);
    }

    public function test_gestionar_un_caso_ya_cerrado_no_revienta_ni_reescribe_la_fecha(): void
    {
        $ctx = $this->escenario();
        $finalizado = $this->crearEstadoCasoEn($ctx['proyecto'], 'FINALIZADO', true);
        $resultado = $this->crearResultado($ctx['proyecto'], 'PAGO_TOTAL', (int) $finalizado->id);

        $this->registrarGestion($ctx, $resultado);
        $cerradoPrimero = DB::table('casos')->where('id', $ctx['casoId'])->value('cerrado_en');

        // Un caso cerrado se puede seguir gestionando: no debe lanzar
        // TransicionCasoInvalida ni tumbar el registro de la segunda gestión.
        $this->registrarGestion($ctx, $resultado);

        $this->assertSame(
            $cerradoPrimero,
            DB::table('casos')->where('id', $ctx['casoId'])->value('cerrado_en'),
            'La fecha de cierre original no debe reescribirse.'
        );
        $this->assertSame(2, DB::table('gestiones')->where('caso_id', $ctx['casoId'])->count());
    }

    public function test_el_cierre_deja_la_gestion_registrada_igualmente(): void
    {
        $ctx = $this->escenario();
        $finalizado = $this->crearEstadoCasoEn($ctx['proyecto'], 'FINALIZADO', true);
        $resultado = $this->crearResultado($ctx['proyecto'], 'PAGO_TOTAL', (int) $finalizado->id);

        $this->registrarGestion($ctx, $resultado);

        $this->assertSame(1, DB::table('gestiones')->where('caso_id', $ctx['casoId'])->count());
        $this->assertDatabaseHas('casos', ['id' => $ctx['casoId'], 'estado_caso_id' => $finalizado->id]);
    }
}
