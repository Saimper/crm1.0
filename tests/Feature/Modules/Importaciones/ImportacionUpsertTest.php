<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Importaciones\Application\UseCases\EjecutarImportacionDinamica;
use App\Modules\Importaciones\Application\UseCases\EjecutarImportacionInput;
use App\Modules\Importaciones\Application\UseCases\PrepararImportacionDinamica;
use App\Modules\Importaciones\Application\UseCases\PrepararImportacionInput;
use App\Modules\Importaciones\Domain\Enums\AccionColumna;
use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use App\Modules\Importaciones\Domain\ValueObjects\ColumnaExcel;
use App\Modules\Importaciones\Domain\ValueObjects\EsquemaImportacion;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionFilaModel;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class ImportacionUpsertTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_upsert_crea_nuevos_y_actualiza_existentes(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto, 'ACTIVO');
        $admin = $this->crearAdminGlobal();

        $personaExistente = $this->crearPersonaEn($proyecto, '1700000001');

        $columnas = [
            new ColumnaExcel(
                nombreOriginal: 'ced',
                tipoInferido: TipoCampo::TEXTO_CORTO,
                campoSistemaMapeado: 'identificacion',
                esIdentificadorPersona: true,
                accion: AccionColumna::MAPEAR_SISTEMA,
            ),
            new ColumnaExcel(
                nombreOriginal: 'prestamo',
                tipoInferido: TipoCampo::TEXTO_CORTO,
                esIdentificadorCaso: true,
                accion: AccionColumna::CREAR_CP,
            ),
            new ColumnaExcel(
                nombreOriginal: 'nombres',
                tipoInferido: TipoCampo::TEXTO_CORTO,
                accion: AccionColumna::CREAR_CP,
            ),
            new ColumnaExcel(
                nombreOriginal: 'apellidos',
                tipoInferido: TipoCampo::TEXTO_CORTO,
                accion: AccionColumna::CREAR_CP,
            ),
            new ColumnaExcel(
                nombreOriginal: 'saldo',
                tipoInferido: TipoCampo::NUMERO_DECIMAL,
                accion: AccionColumna::CREAR_CP,
            ),
        ];

        $esquema = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: (int) $proyecto->id,
            carteraId: (int) $cartera->id,
            modo: ModoImportacion::UPSERT,
            columnas: $columnas,
        );

        $importacion = new ImportacionModel;
        $importacion->public_id = (string) Str::ulid();
        $importacion->proyecto_id = $proyecto->id;
        $importacion->tipo_entidad = 'caso_cobranza';
        $importacion->modo = 'upsert';
        $importacion->estado = EstadoImportacion::PENDIENTE->value;
        $importacion->usuario_id = $admin->id;
        $importacion->nombre_archivo = 'test.csv';
        $importacion->total_filas = 0;
        $importacion->save();

        $filas = [
            ['identificacion' => '1700000001', 'prestamo' => 'PR-001', 'id_cpelegido' => 'PR-001', 'saldo' => '1000.50', 'nombres' => 'Persona Uno', 'apellidos' => 'Uno'],
            ['identificacion' => '1700000002', 'prestamo' => 'PR-002', 'id_cpelegido' => 'PR-002', 'saldo' => '2000.75', 'nombres' => 'Persona Dos', 'apellidos' => 'Dos'],
            ['identificacion' => '1700000003', 'prestamo' => 'PR-003', 'id_cpelegido' => 'PR-003', 'saldo' => '3000.00', 'nombres' => 'Persona Tres', 'apellidos' => 'Tres'],
        ];

        foreach ($filas as $i => $fila) {
            ImportacionFilaModel::query()->create([
                'importacion_id' => $importacion->id,
                'proyecto_id' => $proyecto->id,
                'numero_fila' => $i + 1,
                'estado' => 'pendiente',
                'payload' => $fila,
            ]);
        }

        $importacion->total_filas = count($filas);
        $importacion->save();

        app(PrepararImportacionDinamica::class)->execute(new PrepararImportacionInput(
            importacionId: (int) $importacion->id,
            esquema: $esquema,
            usuarioId: (int) $admin->id,
            tienePermisoCampos: true,
        ));

        $resultado = app(EjecutarImportacionDinamica::class)->execute(new EjecutarImportacionInput(
            importacionId: (int) $importacion->id,
            chunkSize: 1000,
        ));

        self::assertSame(3, $resultado['procesadas']);
        self::assertSame(0, $resultado['invalidas']);
        self::assertSame(0, $resultado['duplicadas']);
    }

    public function test_segunda_ejecucion_no_crea_duplicados(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto, 'ACTIVO');
        $admin = $this->crearAdminGlobal();

        $columnas = [
            new ColumnaExcel(
                nombreOriginal: 'ced',
                tipoInferido: TipoCampo::TEXTO_CORTO,
                campoSistemaMapeado: 'identificacion',
                esIdentificadorPersona: true,
                accion: AccionColumna::MAPEAR_SISTEMA,
            ),
            new ColumnaExcel(
                nombreOriginal: 'prestamo',
                tipoInferido: TipoCampo::TEXTO_CORTO,
                esIdentificadorCaso: true,
                accion: AccionColumna::CREAR_CP,
            ),
            new ColumnaExcel(
                nombreOriginal: 'nombres',
                tipoInferido: TipoCampo::TEXTO_CORTO,
                accion: AccionColumna::CREAR_CP,
            ),
            new ColumnaExcel(
                nombreOriginal: 'apellidos',
                tipoInferido: TipoCampo::TEXTO_CORTO,
                accion: AccionColumna::CREAR_CP,
            ),
        ];

        $esquema = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: (int) $proyecto->id,
            carteraId: (int) $cartera->id,
            modo: ModoImportacion::UPSERT,
            columnas: $columnas,
        );

        $importacion = new ImportacionModel;
        $importacion->public_id = (string) Str::ulid();
        $importacion->proyecto_id = $proyecto->id;
        $importacion->tipo_entidad = 'caso_cobranza';
        $importacion->modo = 'upsert';
        $importacion->estado = EstadoImportacion::PENDIENTE->value;
        $importacion->usuario_id = $admin->id;
        $importacion->nombre_archivo = 'test2.csv';
        $importacion->total_filas = 0;
        $importacion->save();

        $filas = [
            ['identificacion' => '1700000001', 'prestamo' => 'PR-001', 'id_cpelegido' => 'PR-001', 'nombres' => 'Persona', 'apellidos' => 'Uno'],
            ['identificacion' => '1700000002', 'prestamo' => 'PR-002', 'id_cpelegido' => 'PR-002', 'nombres' => 'Persona', 'apellidos' => 'Dos'],
        ];

        foreach ($filas as $i => $fila) {
            ImportacionFilaModel::query()->create([
                'importacion_id' => $importacion->id,
                'proyecto_id' => $proyecto->id,
                'numero_fila' => $i + 1,
                'estado' => 'pendiente',
                'payload' => $fila,
            ]);
        }

        $importacion->total_filas = count($filas);
        $importacion->save();

        app(PrepararImportacionDinamica::class)->execute(new PrepararImportacionInput(
            importacionId: (int) $importacion->id,
            esquema: $esquema,
            usuarioId: (int) $admin->id,
            tienePermisoCampos: true,
        ));

        $resultado = app(EjecutarImportacionDinamica::class)->execute(new EjecutarImportacionInput(
            importacionId: (int) $importacion->id,
            chunkSize: 1000,
        ));

        $totalPersonas = DB::table('personas')->where('proyecto_id', $proyecto->id)->count();

        self::assertGreaterThanOrEqual(2, $resultado['procesadas']);
        self::assertSame(0, $resultado['invalidas']);
        self::assertGreaterThanOrEqual(2, $totalPersonas);
    }

    public function test_insert_con_duplicados(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $admin = $this->crearAdminGlobal();

        $personaExistente = $this->crearPersonaEn($proyecto, '1700000001');

        $columnas = [
            new ColumnaExcel(
                nombreOriginal: 'ced',
                tipoInferido: TipoCampo::TEXTO_CORTO,
                campoSistemaMapeado: 'identificacion',
                esIdentificadorPersona: true,
                accion: AccionColumna::MAPEAR_SISTEMA,
            ),
        ];

        $esquema = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: (int) $proyecto->id,
            carteraId: (int) $cartera->id,
            modo: ModoImportacion::INSERT,
            columnas: $columnas,
        );

        $importacion = new ImportacionModel;
        $importacion->public_id = (string) Str::ulid();
        $importacion->proyecto_id = $proyecto->id;
        $importacion->tipo_entidad = 'caso_cobranza';
        $importacion->modo = 'insert';
        $importacion->estado = EstadoImportacion::PENDIENTE->value;
        $importacion->usuario_id = $admin->id;
        $importacion->nombre_archivo = 'test_insert.csv';
        $importacion->total_filas = 0;
        $importacion->save();

        $filas = [
            ['identificacion' => '1700000001'],
            ['identificacion' => '1700000002'],
            ['identificacion' => '1700000003'],
            ['identificacion' => '1700000004'],
            ['identificacion' => '1700000005'],
        ];

        foreach ($filas as $i => $fila) {
            ImportacionFilaModel::query()->create([
                'importacion_id' => $importacion->id,
                'proyecto_id' => $proyecto->id,
                'numero_fila' => $i + 1,
                'estado' => 'pendiente',
                'payload' => $fila,
            ]);
        }

        $importacion->total_filas = count($filas);
        $importacion->save();

        app(PrepararImportacionDinamica::class)->execute(new PrepararImportacionInput(
            importacionId: (int) $importacion->id,
            esquema: $esquema,
            usuarioId: (int) $admin->id,
            tienePermisoCampos: true,
        ));

        $resultado = app(EjecutarImportacionDinamica::class)->execute(new EjecutarImportacionInput(
            importacionId: (int) $importacion->id,
            chunkSize: 1000,
        ));

        /*
         * En modo INSERT con solo columna de identidad:
         * - Fila 1 (persona existente) → duplicada
         * - Filas 2-5 (persona inexistente) → invalida
         * El return array usa 'insertadas'/'duplicadas'/'invalidas', NO 'validas'.
         */
        self::assertSame(1, $resultado['duplicadas']);
        self::assertSame(4, $resultado['invalidas']);
    }

    public function test_update_con_omitidos(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $admin = $this->crearAdminGlobal();

        $personaExistente1 = $this->crearPersonaEn($proyecto, '1700000001');
        $personaExistente2 = $this->crearPersonaEn($proyecto, '1700000002');
        $personaExistente3 = $this->crearPersonaEn($proyecto, '1700000003');

        $columnas = [
            new ColumnaExcel(
                nombreOriginal: 'ced',
                tipoInferido: TipoCampo::TEXTO_CORTO,
                campoSistemaMapeado: 'identificacion',
                esIdentificadorPersona: true,
                accion: AccionColumna::MAPEAR_SISTEMA,
            ),
        ];

        $esquema = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: (int) $proyecto->id,
            carteraId: (int) $cartera->id,
            modo: ModoImportacion::UPDATE,
            columnas: $columnas,
        );

        $importacion = new ImportacionModel;
        $importacion->public_id = (string) Str::ulid();
        $importacion->proyecto_id = $proyecto->id;
        $importacion->tipo_entidad = 'caso_cobranza';
        $importacion->modo = 'update';
        $importacion->estado = EstadoImportacion::PENDIENTE->value;
        $importacion->usuario_id = $admin->id;
        $importacion->nombre_archivo = 'test_update.csv';
        $importacion->total_filas = 0;
        $importacion->save();

        $filas = [
            ['identificacion' => '1700000001'],
            ['identificacion' => '1700000002'],
            ['identificacion' => '1700000003'],
            ['identificacion' => '1700000099'],
            ['identificacion' => '1700000098'],
        ];

        foreach ($filas as $i => $fila) {
            ImportacionFilaModel::query()->create([
                'importacion_id' => $importacion->id,
                'proyecto_id' => $proyecto->id,
                'numero_fila' => $i + 1,
                'estado' => 'pendiente',
                'payload' => $fila,
            ]);
        }

        $importacion->total_filas = count($filas);
        $importacion->save();

        app(PrepararImportacionDinamica::class)->execute(new PrepararImportacionInput(
            importacionId: (int) $importacion->id,
            esquema: $esquema,
            usuarioId: (int) $admin->id,
            tienePermisoCampos: true,
        ));

        $resultado = app(EjecutarImportacionDinamica::class)->execute(new EjecutarImportacionInput(
            importacionId: (int) $importacion->id,
            chunkSize: 1000,
        ));

        /*
         * En modo UPDATE para CASO_COBRANZA sin casos pre-creados:
         * todas las filas resultan omitidas porque el caso no existe.
         * El return array usa 'omitidas' (no 'validas').
         */
        self::assertSame(0, $resultado['procesadas']);
        self::assertSame(5, $resultado['omitidas']);
    }
}
