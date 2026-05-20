<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Importaciones\Application\UseCases\PrepararImportacionDinamica;
use App\Modules\Importaciones\Application\UseCases\PrepararImportacionInput;
use App\Modules\Importaciones\Domain\Contracts\CampoPersonalizadoImportacionRepository;
use App\Modules\Importaciones\Domain\Enums\AccionColumna;
use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use App\Modules\Importaciones\Domain\Exceptions\ImportacionSinPermisoCamposException;
use App\Modules\Importaciones\Domain\ValueObjects\ColumnaExcel;
use App\Modules\Importaciones\Domain\ValueObjects\EsquemaImportacion;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class ImportacionPermisosTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_gestor_sin_permiso_campos_no_puede_avanzar_con_columnas_cp(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $gestor = $this->crearGestor($proyecto);

        $esquema = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: (int) $proyecto->id,
            carteraId: (int) $cartera->id,
            modo: ModoImportacion::UPSERT,
            columnas: [
                new ColumnaExcel(
                    nombreOriginal: 'saldo_extra',
                    tipoInferido: TipoCampo::NUMERO_DECIMAL,
                    accion: AccionColumna::CREAR_CP,
                ),
            ],
        );

        $importacion = new ImportacionModel;
        $importacion->public_id = (string) Str::ulid();
        $importacion->proyecto_id = $proyecto->id;
        $importacion->tipo_entidad = 'caso_cobranza';
        $importacion->modo = 'upsert';
        $importacion->estado = EstadoImportacion::PENDIENTE->value;
        $importacion->usuario_id = $gestor->id;
        $importacion->nombre_archivo = 'test.csv';
        $importacion->total_filas = 0;
        $importacion->save();

        $this->expectException(ImportacionSinPermisoCamposException::class);

        app(PrepararImportacionDinamica::class)->execute(new PrepararImportacionInput(
            importacionId: (int) $importacion->id,
            esquema: $esquema,
            usuarioId: (int) $gestor->id,
            tienePermisoCampos: false,
        ));
    }

    public function test_admin_global_puede_crear_campos_automaticamente(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $admin = $this->crearAdminGlobal();

        $esquema = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: (int) $proyecto->id,
            carteraId: (int) $cartera->id,
            modo: ModoImportacion::UPSERT,
            columnas: [
                new ColumnaExcel(
                    nombreOriginal: 'monto_prestamo',
                    tipoInferido: TipoCampo::NUMERO_DECIMAL,
                    accion: AccionColumna::CREAR_CP,
                ),
            ],
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

        $resultado = app(PrepararImportacionDinamica::class)->execute(new PrepararImportacionInput(
            importacionId: (int) $importacion->id,
            esquema: $esquema,
            usuarioId: (int) $admin->id,
            tienePermisoCampos: true,
        ));

        self::assertGreaterThan(0, $resultado->camposCreados);
    }

    public function test_supervisor_sin_permiso_campos_no_puede_crear_campos(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $supervisor = $this->crearSupervisor($proyecto);

        $esquema = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: (int) $proyecto->id,
            carteraId: (int) $cartera->id,
            modo: ModoImportacion::UPSERT,
            columnas: [
                new ColumnaExcel(
                    nombreOriginal: 'observacion',
                    tipoInferido: TipoCampo::TEXTO_LARGO,
                    accion: AccionColumna::CREAR_CP,
                ),
            ],
        );

        $importacion = new ImportacionModel;
        $importacion->public_id = (string) Str::ulid();
        $importacion->proyecto_id = $proyecto->id;
        $importacion->tipo_entidad = 'caso_cobranza';
        $importacion->modo = 'upsert';
        $importacion->estado = EstadoImportacion::PENDIENTE->value;
        $importacion->usuario_id = $supervisor->id;
        $importacion->nombre_archivo = 'test.csv';
        $importacion->total_filas = 0;
        $importacion->save();

        $this->expectException(ImportacionSinPermisoCamposException::class);

        app(PrepararImportacionDinamica::class)->execute(new PrepararImportacionInput(
            importacionId: (int) $importacion->id,
            esquema: $esquema,
            usuarioId: (int) $supervisor->id,
            tienePermisoCampos: false,
        ));
    }

    public function test_update_sin_permiso_campos_no_lanza_excepcion(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $gestor = $this->crearGestor($proyecto);

        $esquema = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: (int) $proyecto->id,
            carteraId: (int) $cartera->id,
            modo: ModoImportacion::UPDATE,
            columnas: [
                new ColumnaExcel(
                    nombreOriginal: 'saldo_extra',
                    tipoInferido: TipoCampo::NUMERO_DECIMAL,
                    accion: AccionColumna::CREAR_CP,
                ),
            ],
        );

        $importacion = new ImportacionModel;
        $importacion->public_id = (string) Str::ulid();
        $importacion->proyecto_id = $proyecto->id;
        $importacion->tipo_entidad = 'caso_cobranza';
        $importacion->modo = 'update';
        $importacion->estado = EstadoImportacion::PENDIENTE->value;
        $importacion->usuario_id = $gestor->id;
        $importacion->nombre_archivo = 'test.csv';
        $importacion->total_filas = 0;
        $importacion->save();

        $resultado = app(PrepararImportacionDinamica::class)->execute(new PrepararImportacionInput(
            importacionId: (int) $importacion->id,
            esquema: $esquema,
            usuarioId: (int) $gestor->id,
            tienePermisoCampos: false,
        ));

        self::assertGreaterThanOrEqual(0, $resultado->camposReutilizados);
    }
}
