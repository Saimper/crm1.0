<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auditoria;

use App\Modules\Auditoria\Application\Observers\AuditoriaObserver;
use App\Modules\Personas\Infrastructure\Persistence\Models\PersonaModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * El observer recuerda de qué mandante es cada proyecto para no añadir un
 * SELECT a cada escritura auditada. Ese memo es estático, y un estático vive
 * lo que el proceso: aquí se fija que no sobrevive a la petición.
 *
 * Nació de la suite: `RefreshDatabase` vuelve a migrar de cero después de un
 * test que deja la conexión sin transacción, los ids de proyecto se repiten, y
 * el memo atribuía el proyecto nuevo al mandante del viejo — FK rota al
 * auditar la primera persona.
 */
final class MemoDeMandanteDelObserverTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_el_memo_del_mandante_no_sobrevive_a_la_peticion(): void
    {
        $mandanteA = $this->crearMandante();
        $mandanteB = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandanteA);
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        $primera = $this->auditarUnaPersona($proyecto, '5100000001');
        self::assertSame((int) $mandanteA->id, (int) $primera->mandante_id);

        // Un cambio que el memo no puede ver: el proyecto pasa a otro cliente
        // por debajo (en la aplicación el mandante no es editable; en la base sí).
        DB::table('proyectos')->where('id', $proyecto->id)->update(['mandante_id' => $mandanteB->id]);

        // Dentro de la MISMA petición se sirve lo recordado: es el ahorro.
        $segunda = $this->auditarUnaPersona($proyecto, '5100000002');
        self::assertSame((int) $mandanteA->id, (int) $segunda->mandante_id, 'Dentro de la petición el memo manda.');

        // Una petición nueva rebindea `request` y tira el memo.
        $this->get('/no-existe')->assertNotFound();

        $tercera = $this->auditarUnaPersona($proyecto, '5100000003');
        self::assertSame((int) $mandanteB->id, (int) $tercera->mandante_id, 'Tras la petición se vuelve a leer de la base.');
    }

    public function test_olvidar_mandantes_fuerza_la_relectura_en_la_misma_peticion(): void
    {
        $mandanteA = $this->crearMandante();
        $mandanteB = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandanteA);
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        $this->auditarUnaPersona($proyecto, '5200000001');
        DB::table('proyectos')->where('id', $proyecto->id)->update(['mandante_id' => $mandanteB->id]);

        AuditoriaObserver::olvidarMandantes();

        $fila = $this->auditarUnaPersona($proyecto, '5200000002');
        self::assertSame((int) $mandanteB->id, (int) $fila->mandante_id);
    }

    /** Crea una persona (modelo observado) y devuelve la fila de auditoría que dejó. */
    private function auditarUnaPersona(stdClass $proyecto, string $identificacion): stdClass
    {
        $persona = PersonaModel::query()->create([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'tipo_persona' => 'fisica',
            'tipo_identificacion_id' => (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id'),
            'identificacion' => $identificacion,
            'nombres' => 'Memo',
            'apellidos' => 'Observer',
        ]);

        $fila = DB::table('auditorias')
            ->where('entidad_tipo', 'personas')
            ->where('entidad_id', $persona->id)
            ->where('evento', 'creado')
            ->first();

        self::assertNotNull($fila, 'El observer no dejó rastro: el escenario no prueba nada.');

        return $fila;
    }
}
