<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Casos;

use App\Modules\Casos\Infrastructure\Http\Livewire\VistaDeTrabajo;
use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\UseCases\RegistrarGestion;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * El historial de la vista de trabajo, acotado a lo que fue conversación.
 *
 * Un caso con treinta intentos y dos contactos cuenta su historia en esos dos.
 * Lo que decide cuál es cuál es `resultados.es_contacto_efectivo`, que cada
 * proyecto declara en su catálogo: no el nombre del resultado, que en un
 * proyecto puede ser «Contacto con tercero» y en otro «Habló con el titular».
 */
final class HistorialEfectivasTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_el_filtro_deja_solo_las_que_llegaron_a_la_persona(): void
    {
        [$proyecto, $persona, $casoId, $cascada] = $this->escenario();

        $resultadoSeco = $this->resultadoNoEfectivo($proyecto, $cascada);

        $efectiva = $this->registrarGestion($proyecto, $persona, $casoId, $cascada, $cascada['resultado_id'], '2026-09-08 09:00:00');
        $seca = $this->registrarGestion($proyecto, $persona, $casoId, $cascada, $resultadoSeco, '2026-09-08 11:00:00');

        $this->activarProyecto($proyecto);
        $componente = Livewire::actingAs($this->crearGestor($proyecto))
            ->test(VistaDeTrabajo::class, ['persona' => $persona->public_id]);

        $this->assertSame([$seca, $efectiva], $this->publicIds($componente->viewData('historial')), 'Sin filtro salen las dos.');

        $componente->call('alternarSoloEfectivas');

        $this->assertSame([$efectiva], $this->publicIds($componente->viewData('historial')));

        $componente->call('alternarSoloEfectivas');

        $this->assertCount(2, $componente->viewData('historial'), 'Y el interruptor vuelve.');
    }

    /** @return array{0: stdClass, 1: stdClass, 2: int, 3: array<string, int|null>} */
    private function escenario(): array
    {
        $proyecto = $this->crearProyectoCobranza();
        $persona = $this->crearPersonaEn($proyecto);
        $casoId = $this->crearCasoEn($proyecto, ['persona' => $persona]);

        return [$proyecto, $persona, $casoId, $this->crearCascadaGestionEn($proyecto)];
    }

    /**
     * Un resultado del mismo proyecto que NO es contacto efectivo, y con un
     * nombre que la lista en español de antes habría dado por bueno.
     *
     * @param  array<string, int|null>  $cascada
     */
    private function resultadoNoEfectivo(stdClass $proyecto, array $cascada): int
    {
        $id = (int) DB::table('resultados')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'CONTACTO_TERCERO',
            'nombre' => 'Contacto efectivo con tercero',
            'es_contacto_efectivo' => false,
            'requiere_compromiso' => false,
            'requiere_causa' => false,
            'activo' => true,
            'orden' => 90,
        ]);

        DB::table('resultado_tipo_gestion')->insert([
            'proyecto_id' => $proyecto->id,
            'tipo_gestion_id' => $cascada['tipo_gestion_id'],
            'resultado_id' => $id,
        ]);

        return $id;
    }

    /** @param  array<string, int|null>  $cascada */
    private function registrarGestion(stdClass $proyecto, stdClass $persona, int $casoId, array $cascada, int $resultadoId, string $creadaEn): string
    {
        $publicId = (string) Str::ulid();

        app(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId: $publicId,
            proyectoId: (int) $proyecto->id,
            casoId: $casoId,
            personaId: (int) $persona->id,
            contactoId: null,
            canalId: $cascada['canal_id'],
            tipoGestionId: $cascada['tipo_gestion_id'],
            resultadoId: $resultadoId,
            motivoNoContactoId: null,
            causaId: null,
            usuarioId: (int) $this->crearGestor($proyecto)->id,
            notas: null,
            duracion: null,
            creadaEn: new DateTimeImmutable($creadaEn),
        ));

        return $publicId;
    }

    /**
     * @param  iterable<int, stdClass>  $filas
     * @return list<string>
     */
    private function publicIds(iterable $filas): array
    {
        $ids = [];
        foreach ($filas as $fila) {
            $ids[] = (string) $fila->public_id;
        }

        return $ids;
    }
}
