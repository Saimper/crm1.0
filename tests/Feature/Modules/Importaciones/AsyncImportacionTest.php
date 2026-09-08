<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use App\Models\User;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Importaciones\Application\UseCases\CancelarImportacion;
use App\Modules\Importaciones\Application\UseCases\ConsultarProgresoImportacion;
use App\Modules\Importaciones\Application\UseCases\EjecutarImportacionDinamica;
use App\Modules\Importaciones\Application\UseCases\EncolarImportacion;
use App\Modules\Importaciones\Application\UseCases\PrepararImportacionDinamica;
use App\Modules\Importaciones\Application\UseCases\PrepararImportacionInput;
use App\Modules\Importaciones\Domain\Enums\AccionColumna;
use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use App\Modules\Importaciones\Domain\Exceptions\ImportacionEnCursoNoEditable;
use App\Modules\Importaciones\Domain\ValueObjects\ColumnaExcel;
use App\Modules\Importaciones\Domain\ValueObjects\EsquemaImportacion;
use App\Modules\Importaciones\Infrastructure\Jobs\EjecutarImportacionJob;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class AsyncImportacionTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_encolar_dispatches_job_y_marca_procesando(): void
    {
        Queue::fake();

        [$proyecto, $usuario] = $this->contextoProyectoCobranza();
        $importacionId = $this->crearImportacionPreparada($proyecto, $usuario);

        app(EncolarImportacion::class)->execute($importacionId, ModoImportacion::MERGE);

        Queue::assertPushed(EjecutarImportacionJob::class, fn ($job) => $job->importacionId === $importacionId);

        $i = DB::table('importaciones')->where('id', $importacionId)->first();
        $this->assertSame('procesando', $i->estado);
        $this->assertNotNull($i->iniciado_en);
        $this->assertSame('merge', $i->modo);
    }

    public function test_encolar_dos_veces_falla_con_estado_no_editable(): void
    {
        Queue::fake();

        [$proyecto, $usuario] = $this->contextoProyectoCobranza();
        $importacionId = $this->crearImportacionPreparada($proyecto, $usuario);

        app(EncolarImportacion::class)->execute($importacionId, ModoImportacion::MERGE);

        $this->expectException(ImportacionEnCursoNoEditable::class);
        app(EncolarImportacion::class)->execute($importacionId, ModoImportacion::MERGE);
    }

    public function test_consultar_progreso_devuelve_dto_con_porcentaje(): void
    {
        [$proyecto, $usuario] = $this->contextoProyectoCobranza();
        $importacionId = (int) DB::table('importaciones')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'tipo_entidad' => 'persona',
            'modo' => 'merge',
            'estado' => EstadoImportacion::PROCESANDO->value,
            'usuario_id' => $usuario->id,
            'nombre_archivo' => 'p.csv',
            'total_filas' => 100,
            'procesadas' => 30,
            'invalidas' => 10,
            'duplicadas' => 5,
        ]);

        $progreso = app(ConsultarProgresoImportacion::class)->execute($importacionId);

        $this->assertSame(EstadoImportacion::PROCESANDO, $progreso->estado);
        $this->assertSame(45, $progreso->porcentaje());
        $this->assertTrue($progreso->enCurso());
    }

    public function test_idempotencia_job_re_encolado_no_duplica_filas(): void
    {
        [$proyecto, $usuario] = $this->contextoProyectoCobranza();
        $this->crearPersonaEn($proyecto, '7700000099');

        $importacionId = $this->crearImportacionPreparada($proyecto, $usuario, [
            ['identificacion' => '7700000099', 'nombre' => 'Idem'],
        ]);

        $this->ejecutarJob($importacionId);

        $personasAntes = (int) DB::table('personas')->where('identificacion', '7700000099')->count();
        $this->assertSame(1, $personasAntes);

        $this->ejecutarJob($importacionId);

        $personasDespues = (int) DB::table('personas')->where('identificacion', '7700000099')->count();
        $this->assertSame(1, $personasDespues, 'Job re-ejecutado no debe duplicar filas');

        $i = DB::table('importaciones')->where('id', $importacionId)->first();
        $this->assertSame('completada', $i->estado);
    }

    public function test_dos_importaciones_distintas_avanzan_independientes(): void
    {
        [$proyectoCob, $usuario] = $this->contextoProyectoCobranza();
        $proyectoCx = $this->crearProyectoCx();

        $impA = $this->crearImportacionPreparada($proyectoCob, $usuario, [
            ['identificacion' => '8800000001', 'nombre' => 'Proyecto A'],
        ]);
        $impB = $this->crearImportacionPreparada($proyectoCx, $usuario, [
            ['identificacion' => '8800000002', 'nombre' => 'Proyecto B'],
        ]);

        $this->ejecutarJob($impA);
        $this->ejecutarJob($impB);

        $a = DB::table('importaciones')->where('id', $impA)->first();
        $b = DB::table('importaciones')->where('id', $impB)->first();

        $this->assertSame('completada', $a->estado);
        $this->assertSame('completada', $b->estado);
        $this->assertSame(1, (int) $a->procesadas);
        $this->assertSame(1, (int) $b->procesadas);

        $this->assertDatabaseHas('personas', ['identificacion' => '8800000001', 'proyecto_id' => $proyectoCob->id]);
        $this->assertDatabaseHas('personas', ['identificacion' => '8800000002', 'proyecto_id' => $proyectoCx->id]);
    }

    public function test_cancelacion_marca_estado_y_evita_procesamiento(): void
    {
        [$proyecto, $usuario] = $this->contextoProyectoCobranza();
        $importacionId = $this->crearImportacionPreparada($proyecto, $usuario, [
            ['identificacion' => '9999000001', 'nombre' => 'Cancelable'],
        ]);

        DB::table('importaciones')->where('id', $importacionId)->update([
            'estado' => EstadoImportacion::PROCESANDO->value,
        ]);

        app(CancelarImportacion::class)->execute($importacionId);

        $i = DB::table('importaciones')->where('id', $importacionId)->first();
        $this->assertSame('cancelada', $i->estado);

        $this->ejecutarJob($importacionId);

        $personas = (int) DB::table('personas')->where('identificacion', '9999000001')->count();
        $this->assertSame(0, $personas, 'Job cancelado no debe insertar');
    }

    /**
     * @return array{0: stdClass, 1: User}
     */
    private function contextoProyectoCobranza(): array
    {
        $proyecto = $this->crearProyectoCobranza();
        $usuario = $this->crearSupervisor($proyecto);
        $this->actingAs($usuario);

        return [$proyecto, $usuario];
    }

    /**
     * Una importación en estado `preparada` (lo único que `EncolarImportacion`
     * admite), con su esquema dinámico ya persistido.
     *
     * El target es el caso del tipo del proyecto y no `persona`: el ejecutor
     * dinámico sólo sabe crear casos, y una persona nueva nace como efecto de
     * crear el caso. Cada fila trae identificación (identificador de persona),
     * nombre y el código de expediente que hace único al caso.
     *
     * @param  list<array{identificacion: string, nombre: string}>  $filas
     */
    private function crearImportacionPreparada(stdClass $proyecto, User $usuario, array $filas = []): int
    {
        $cartera = $this->crearCarteraEn($proyecto);
        $this->crearEstadoCasoEn($proyecto, 'ABIERTO_'.strtoupper(Str::random(4)));

        $target = $this->targetDe($proyecto);

        $importacionId = (int) DB::table('importaciones')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'tipo_entidad' => $target->tipoEntidadDb(),
            'modo' => 'merge',
            'estado' => EstadoImportacion::PENDIENTE->value,
            'usuario_id' => $usuario->id,
            'nombre_archivo' => 'p.csv',
            'total_filas' => count($filas),
        ]);

        foreach (array_values($filas) as $indice => $fila) {
            DB::table('importacion_filas')->insert([
                'importacion_id' => $importacionId,
                'proyecto_id' => $proyecto->id,
                'numero_fila' => $indice + 1,
                'estado' => 'pendiente',
                'payload' => json_encode([
                    'identificacion' => $fila['identificacion'],
                    'nombres' => $fila['nombre'],
                    'expediente' => 'EXP-'.$fila['identificacion'],
                    'id_cpelegido' => 'EXP-'.$fila['identificacion'],
                ], JSON_THROW_ON_ERROR),
            ]);
        }

        app(PrepararImportacionDinamica::class)->execute(new PrepararImportacionInput(
            importacionId: $importacionId,
            esquema: new EsquemaImportacion(
                target: $target,
                proyectoId: (int) $proyecto->id,
                carteraId: (int) $cartera->id,
                modo: ModoImportacion::MERGE,
                columnas: [
                    new ColumnaExcel(
                        nombreOriginal: 'cedula',
                        tipoInferido: TipoCampo::TEXTO_CORTO,
                        campoSistemaMapeado: 'identificacion',
                        esIdentificadorPersona: true,
                        accion: AccionColumna::MAPEAR_SISTEMA,
                    ),
                    new ColumnaExcel(
                        nombreOriginal: 'expediente',
                        tipoInferido: TipoCampo::TEXTO_CORTO,
                        esIdentificadorCaso: true,
                        accion: AccionColumna::CREAR_CP,
                    ),
                    new ColumnaExcel(
                        nombreOriginal: 'nombres',
                        tipoInferido: TipoCampo::TEXTO_CORTO,
                        accion: AccionColumna::CREAR_CP,
                    ),
                ],
            ),
            usuarioId: (int) $usuario->id,
            tienePermisoCampos: true,
        ));

        return $importacionId;
    }

    private function targetDe(stdClass $proyecto): TargetImportacion
    {
        return match ($proyecto->tipo_operacion) {
            'cx' => TargetImportacion::CASO_TICKET_CX,
            'venta' => TargetImportacion::CASO_LEAD_VENTA,
            'servicio' => TargetImportacion::CASO_SERVICIO,
            default => TargetImportacion::CASO_COBRANZA,
        };
    }

    /** El job resuelve su ejecutor por inyección, no lo construye. */
    private function ejecutarJob(int $importacionId, string $modo = 'merge'): void
    {
        (new EjecutarImportacionJob($importacionId, $modo))
            ->handle(app(EjecutarImportacionDinamica::class));
    }
}
