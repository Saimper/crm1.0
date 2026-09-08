<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Asignaciones;

use App\Modules\Asignaciones\Application\DTOs\RegistrarAsignacionInput;
use App\Modules\Asignaciones\Application\UseCases\AsignarCasosAEquipo;
use App\Modules\Asignaciones\Application\UseCases\ReasignarAsignacionAUsuario;
use App\Modules\Asignaciones\Application\UseCases\RegistrarAsignacion;
use App\Modules\Asignaciones\Domain\Exceptions\TransicionAsignacionInvalida;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;
use Tests\Support\EscenarioMultiMandante;
use Tests\TestCase;

/**
 * Una cuenta tiene un dueño en su proyecto.
 *
 * Antes el único era `(campana_id, caso_id)`, así que el esquema admitía dos
 * asignaciones vivas de la misma cuenta en campañas distintas — mientras
 * `AutoasignarCaso::duenioActual`, `VistaDeTrabajo::duenioDelCaso` y
 * `Bandeja::consultaPool` resolvían el dueño mirando sólo `caso_id`. El código
 * ya daba por hecho lo que el esquema no garantizaba, y no había test que lo
 * dijera: esa es la razón de este fichero.
 *
 * El primer caso ataca el índice con `insert` crudos, saltándose el dominio a
 * propósito: si mañana alguien relaja la comprobación de PHP, el que tiene que
 * gritar es MySQL.
 */
final class UnaCuentaUnDuenioPorProyectoTest extends TestCase
{
    use EscenarioMultiMandante;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_la_base_rechaza_una_segunda_asignacion_de_la_misma_cuenta(): void
    {
        $proyecto = $this->crearProyectoCobranza($this->crearMandante());
        $casoId = $this->crearCaso($proyecto);
        $uno = $this->crearGestor($proyecto);
        $otro = $this->crearGestor($proyecto);

        $this->insertarAsignacion($proyecto, $casoId, (int) $uno->id);

        $this->expectException(QueryException::class);
        $this->insertarAsignacion($proyecto, $casoId, (int) $otro->id);
    }

    public function test_el_use_case_no_registra_dos_veces_la_misma_cuenta(): void
    {
        $proyecto = $this->crearProyectoCobranza($this->crearMandante());
        $casoId = $this->crearCaso($proyecto);
        $uno = $this->crearGestor($proyecto);
        $otro = $this->crearGestor($proyecto);

        $this->registrar($proyecto, $casoId, (int) $uno->id);

        try {
            $this->registrar($proyecto, $casoId, (int) $otro->id);
            $this->fail('Se registró una segunda asignación para la misma cuenta.');
        } catch (TransicionAsignacionInvalida) {
            // Lo esperado: el use case corta antes de llegar al índice.
        }

        $this->assertSame(1, (int) DB::table('asignaciones')->where('caso_id', $casoId)->count());
    }

    public function test_el_reparto_masivo_no_pisa_la_cuenta_que_ya_tiene_duenio(): void
    {
        $proyecto = $this->crearProyectoCobranza($this->crearMandante());
        $tomada = $this->crearCaso($proyecto);
        $libre = $this->crearCaso($proyecto);

        $duenio = $this->crearGestor($proyecto);
        $this->insertarAsignacion($proyecto, $tomada, (int) $duenio->id);

        $delEquipo = $this->crearGestor($proyecto);
        $equipoId = $this->crearEquipoCon($proyecto, (int) $delEquipo->id);

        $r = app(AsignarCasosAEquipo::class)->execute(
            proyectoId: (int) $proyecto->id,
            equipoId: $equipoId,
            limite: 0,
        );

        $this->assertSame(1, $r->asignadas, 'Sólo la cuenta libre era elegible.');
        $this->assertSame(
            (int) $duenio->id,
            (int) DB::table('asignaciones')->where('caso_id', $tomada)->value('usuario_id'),
            'El reparto por lotes le quitó al asesor una cuenta que ya era suya.'
        );
        $this->assertSame(
            (int) $delEquipo->id,
            (int) DB::table('asignaciones')->where('caso_id', $libre)->value('usuario_id')
        );
    }

    public function test_reasignar_cambia_el_duenio_sin_crear_una_segunda_fila(): void
    {
        $proyecto = $this->crearProyectoCobranza($this->crearMandante());
        $casoId = $this->crearCaso($proyecto);
        $uno = $this->crearGestor($proyecto);
        $otro = $this->crearGestor($proyecto);

        $asignacionId = $this->registrar($proyecto, $casoId, (int) $uno->id);

        app(ReasignarAsignacionAUsuario::class)->execute((int) $proyecto->id, $asignacionId, (int) $otro->id);

        $this->assertSame(1, (int) DB::table('asignaciones')->where('caso_id', $casoId)->count());
        $this->assertSame(
            (int) $otro->id,
            (int) DB::table('asignaciones')->where('id', $asignacionId)->value('usuario_id')
        );
    }

    /**
     * El único empieza por `proyecto_id`, así que acota, no restringe de más:
     * dos clientes con una cuenta cada uno conviven sin estorbarse (§12).
     */
    public function test_dos_mandantes_pueden_tener_cada_uno_su_asignacion(): void
    {
        $proyectoA = $this->crearProyectoCobranza($this->crearMandante());
        $proyectoB = $this->crearProyectoCobranza($this->crearMandante());

        $casoA = $this->crearCaso($proyectoA);
        $casoB = $this->crearCaso($proyectoB);

        $this->registrar($proyectoA, $casoA, (int) $this->crearGestor($proyectoA)->id);
        $this->registrar($proyectoB, $casoB, (int) $this->crearGestor($proyectoB)->id);

        $this->assertSame(2, (int) DB::table('asignaciones')->count());
    }

    private function crearCaso(stdClass $proyecto): int
    {
        return (int) DB::table('casos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'cartera_id' => $this->crearCarteraEn($proyecto)->id,
            'persona_id' => $this->crearPersonaEn($proyecto)->id,
            'tipo_caso' => 'cobranza',
            'estado_caso_id' => $this->crearEstadoCasoEn($proyecto)->id,
            'fecha_ingreso' => '2026-09-01',
        ]);
    }

    private function insertarAsignacion(stdClass $proyecto, int $casoId, int $usuarioId): void
    {
        DB::table('asignaciones')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'caso_id' => $casoId,
            'usuario_id' => $usuarioId,
            'fecha_asignacion' => '2026-09-01',
        ]);
    }

    private function registrar(stdClass $proyecto, int $casoId, int $usuarioId): int
    {
        return app(RegistrarAsignacion::class)->execute(new RegistrarAsignacionInput(
            publicId: (string) Str::ulid(),
            proyectoId: (int) $proyecto->id,
            casoId: $casoId,
            usuarioId: $usuarioId,
            fechaAsignacion: new DateTimeImmutable('2026-09-01'),
            prioridad: 100,
            creadaEn: new DateTimeImmutable('2026-09-01'),
        ));
    }

    private function crearEquipoCon(stdClass $proyecto, int $usuarioId): int
    {
        $equipoId = (int) DB::table('equipos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'codigo' => 'EQ_'.strtoupper(Str::random(6)),
            'nombre' => 'Equipo',
            'activo' => true,
        ]);

        DB::table('equipo_usuario')->insert([
            'proyecto_id' => $proyecto->id,
            'equipo_id' => $equipoId,
            'usuario_id' => $usuarioId,
            'activo' => true,
        ]);

        return $equipoId;
    }
}
