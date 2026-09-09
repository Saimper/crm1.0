<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use App\Models\User;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Importaciones\Application\UseCases\EncolarImportacion;
use App\Modules\Importaciones\Domain\Contracts\CampoPersonalizadoImportacionRepository;
use App\Modules\Importaciones\Domain\Enums\AccionColumna;
use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use App\Modules\Importaciones\Domain\Events\ImportacionFallada;
use App\Modules\Importaciones\Domain\ValueObjects\ColumnaExcel;
use App\Modules\Importaciones\Domain\ValueObjects\EsquemaImportacion;
use App\Modules\Personas\Application\UseCases\RegistrarPersona;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PDOException;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Comprobado en local (importación 24): `error_global` guardaba la
 * QueryException cruda —«SQLSTATE[21S01]… SQL: insert into `personas` (…)
 * values (8-990-429, ALEXIS SANTOS…)»— y por fila `mensaje_error` hacía lo
 * mismo. Es decir, los datos del cliente copiados a una columna que se pinta.
 *
 * La cola corre en `sync` en la suite, así que `EncolarImportacion` ejecuta el
 * job aquí mismo y se puede mirar lo que dejó.
 */
final class FalloImportacionSinDatosPersonalesTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    private const SIN_SQL = '/insert into|SQL:/i';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_una_importacion_encolada_sin_esquema_queda_fallida_con_un_motivo_legible(): void
    {
        [$proyecto, $usuario] = $this->contexto();
        $importacionId = $this->importacionPreparada($proyecto, $usuario, esquema: null);

        app(EncolarImportacion::class)->execute($importacionId, ModoImportacion::UPSERT);

        $importacion = DB::table('importaciones')->where('id', $importacionId)->first();

        $this->assertSame('fallida', (string) $importacion->estado);
        $this->assertNotNull($importacion->terminado_en);
        $this->assertSame('La importación no tiene esquema configurado.', (string) $importacion->error_global);
        $this->assertDoesNotMatchRegularExpression(self::SIN_SQL, (string) $importacion->error_global);
    }

    /**
     * Lo que se escribió ANTES de arreglar esto sigue en la base, se pinta en
     * el historial del asistente y viaja en el CSV de filas rechazadas. La
     * migración lo redacta con el mismo criterio del descriptor: se queda el
     * diagnóstico, se van los valores.
     */
    public function test_la_migracion_borra_el_sql_que_quedo_guardado_de_antes(): void
    {
        [$proyecto, $usuario] = $this->contexto();
        $importacionId = $this->importacionPreparada($proyecto, $usuario, esquema: null);

        $crudo = "SQLSTATE[21S01]: Insert value list does not match column list: 1136 Column count doesn't match "
            ."(Connection: mysql, SQL: insert into `personas` (`identificacion`, `nombres`) values ('8-990-429', 'ALEXIS SANTOS'))";

        DB::table('importaciones')->where('id', $importacionId)->update(['error_global' => $crudo]);
        DB::table('importacion_filas')->insert([
            'importacion_id' => $importacionId,
            'proyecto_id' => $proyecto->id,
            'numero_fila' => 1,
            'estado' => 'invalida',
            'payload' => json_encode(['identificacion' => '8-990-429']),
            'mensaje_error' => $crudo,
        ]);
        DB::table('importacion_filas')->insert([
            'importacion_id' => $importacionId,
            'proyecto_id' => $proyecto->id,
            'numero_fila' => 2,
            'estado' => 'invalida',
            'payload' => json_encode(['identificacion' => '8-990-430']),
            'mensaje_error' => 'Los días de mora (99999) superan los 40 años.',
        ]);

        $migracion = require database_path('migrations/2026_09_09_122000_importaciones_redactar_errores_con_sql.php');
        $migracion->up();

        $global = (string) DB::table('importaciones')->where('id', $importacionId)->value('error_global');
        $deLaFila = (string) DB::table('importacion_filas')->where('numero_fila', 1)->value('mensaje_error');

        foreach ([$global, $deLaFila] as $texto) {
            $this->assertDoesNotMatchRegularExpression(self::SIN_SQL, $texto);
            $this->assertStringNotContainsString('8-990-429', $texto);
            $this->assertStringNotContainsString('ALEXIS SANTOS', $texto);
            $this->assertStringContainsString('SQLSTATE[21S01]', $texto, 'El diagnóstico se queda: es lo único que explica la caída.');
        }

        $this->assertSame(
            'Los días de mora (99999) superan los 40 años.',
            (string) DB::table('importacion_filas')->where('numero_fila', 2)->value('mensaje_error'),
            'Un motivo de dominio no lleva datos dentro y se deja como está.',
        );
    }

    public function test_un_fallo_de_base_de_datos_en_el_lote_no_deja_el_sql_en_ninguna_parte(): void
    {
        [$proyecto, $usuario] = $this->contexto();
        $importacionId = $this->importacionPreparada($proyecto, $usuario, $this->esquemaCobranza($proyecto), [
            ['identificacion' => '8-990-429', 'nombres' => 'ALEXIS SANTOS', 'cuenta' => 'PR-1', 'id_cpelegido' => 'PR-1'],
        ]);

        // El mapa de campos se carga al abrir cada lote, fuera del try/catch
        // por fila: lo que lance aquí tumba el lote entero.
        $repo = $this->createMock(CampoPersonalizadoImportacionRepository::class);
        $repo->method('obtenerMapaCampos')->willThrowException($this->queryExceptionConDatos());
        $this->app->instance(CampoPersonalizadoImportacionRepository::class, $repo);

        Log::spy();
        $fallosDelJob = [];
        Event::listen(JobFailed::class, function (JobFailed $evento) use (&$fallosDelJob): void {
            $fallosDelJob[] = $evento->exception;
        });
        $fallada = null;
        Event::listen(ImportacionFallada::class, function (ImportacionFallada $evento) use (&$fallada): void {
            $fallada = $evento;
        });

        app(EncolarImportacion::class)->execute($importacionId, ModoImportacion::UPSERT);

        $importacion = DB::table('importaciones')->where('id', $importacionId)->first();

        $this->assertSame('fallida', (string) $importacion->estado);
        $this->assertMatchesRegularExpression('/^La base de datos rechazó el lote \(SQLSTATE 23000, error 1062\)\. Referencia [0-9a-f]{8}\.$/', (string) $importacion->error_global);
        $this->assertDoesNotMatchRegularExpression(self::SIN_SQL, (string) $importacion->error_global);
        $this->assertStringNotContainsString('ALEXIS', (string) $importacion->error_global);

        foreach (DB::table('importacion_filas')->where('importacion_id', $importacionId)->get() as $fila) {
            $this->assertDoesNotMatchRegularExpression(self::SIN_SQL, (string) $fila->mensaje_error);
        }

        $this->assertCount(1, $fallosDelJob, 'El job queda failed una vez, sin reintentos.');
        $this->assertDoesNotMatchRegularExpression(self::SIN_SQL, (string) $fallosDelJob[0]->getMessage());
        $this->assertNull($fallosDelJob[0]->getPrevious(), 'Sin `previous`: el worker no vuelve a loguear el SQL crudo.');

        $this->assertNotNull($fallada);
        $this->assertStringNotContainsString('ALEXIS', $fallada->motivo);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $fallada->referencia);

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(fn (string $mensaje, array $contexto): bool => ! str_contains(json_encode($contexto, JSON_THROW_ON_ERROR), 'ALEXIS')
                && $contexto['importacion_id'] === $importacionId
                && $contexto['proyecto_id'] === (int) $proyecto->id);
    }

    public function test_un_fallo_de_base_de_datos_en_una_fila_deja_la_fila_invalida_sin_el_sql(): void
    {
        [$proyecto, $usuario] = $this->contexto();
        $importacionId = $this->importacionPreparada($proyecto, $usuario, $this->esquemaCobranza($proyecto), [
            ['identificacion' => '8-990-429', 'nombres' => 'ALEXIS SANTOS', 'cuenta' => 'PR-1', 'id_cpelegido' => 'PR-1'],
        ]);

        $registrar = $this->createMock(RegistrarPersona::class);
        $registrar->method('execute')->willThrowException($this->queryExceptionConDatos());
        $this->app->instance(RegistrarPersona::class, $registrar);

        app(EncolarImportacion::class)->execute($importacionId, ModoImportacion::UPSERT);

        $importacion = DB::table('importaciones')->where('id', $importacionId)->first();
        $fila = DB::table('importacion_filas')->where('importacion_id', $importacionId)->first();

        $this->assertSame('completada', (string) $importacion->estado, 'Una fila mala no tumba el lote.');
        $this->assertSame(1, (int) $importacion->invalidas);
        $this->assertSame('invalida', (string) $fila->estado);
        $this->assertSame('La base de datos rechazó la fila (SQLSTATE 23000, error 1062).', (string) $fila->mensaje_error);
        $this->assertDoesNotMatchRegularExpression(self::SIN_SQL, (string) $fila->mensaje_error);
    }

    /** @return array{0: stdClass, 1: User} */
    private function contexto(): array
    {
        $proyecto = $this->crearProyectoCobranza();
        $usuario = $this->crearSupervisor($proyecto);
        $this->actingAs($usuario);

        return [$proyecto, $usuario];
    }

    private function esquemaCobranza(stdClass $proyecto): EsquemaImportacion
    {
        $cartera = $this->crearCarteraEn($proyecto);
        $this->crearEstadoCasoEn($proyecto, 'ABIERTO');

        return new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: (int) $proyecto->id,
            carteraId: (int) $cartera->id,
            modo: ModoImportacion::UPSERT,
            columnas: [
                new ColumnaExcel('CEDULA', TipoCampo::TEXTO_CORTO, 'identificacion', true, false, AccionColumna::MAPEAR_SISTEMA),
                new ColumnaExcel('NOMBRE', TipoCampo::TEXTO_CORTO, 'nombres', false, false, AccionColumna::MAPEAR_SISTEMA),
                new ColumnaExcel('CUENTA', TipoCampo::TEXTO_CORTO, null, false, true, AccionColumna::CREAR_CP),
            ],
        );
    }

    /**
     * Una importación `preparada` con el esquema (o su ausencia) escrito a
     * mano, para no pasar por `PrepararImportacionDinamica` —que también usa el
     * repositorio que aquí se hace fallar—.
     *
     * @param  list<array<string, string>>  $filas
     */
    private function importacionPreparada(stdClass $proyecto, User $usuario, ?EsquemaImportacion $esquema, array $filas = []): int
    {
        $importacionId = (int) DB::table('importaciones')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'tipo_entidad' => 'caso_cobranza',
            'modo' => 'upsert',
            'estado' => EstadoImportacion::PREPARADA->value,
            'usuario_id' => $usuario->id,
            'nombre_archivo' => 'base.csv',
            'total_filas' => count($filas),
            'esquema' => $esquema?->serializar(),
        ]);

        foreach (array_values($filas) as $indice => $fila) {
            DB::table('importacion_filas')->insert([
                'importacion_id' => $importacionId,
                'proyecto_id' => $proyecto->id,
                'numero_fila' => $indice + 1,
                'estado' => 'pendiente',
                'payload' => json_encode($fila, JSON_THROW_ON_ERROR),
            ]);
        }

        return $importacionId;
    }

    private function queryExceptionConDatos(): QueryException
    {
        $pdo = new PDOException("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '8-990-429' for key 'personas_unique'");
        $pdo->errorInfo = ['23000', 1062, "Duplicate entry '8-990-429' for key 'personas_unique'"];

        return new QueryException(
            'mysql',
            'insert into `personas` (`identificacion`, `nombres`) values (?, ?)',
            ['8-990-429', 'ALEXIS SANTOS'],
            $pdo,
        );
    }
}
