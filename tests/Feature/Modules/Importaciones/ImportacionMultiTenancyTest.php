<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Importaciones\Application\UseCases\ConsultarProgresoImportacion;
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

final class ImportacionMultiTenancyTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_import_en_proyecto_a_no_crea_campos_visibles_desde_proyecto_b(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCx();
        $carteraA = $this->crearCarteraEn($proyectoA);
        $carteraB = $this->crearCarteraEn($proyectoB);

        $admin = $this->crearAdminGlobal();

        $esquema = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: (int) $proyectoA->id,
            carteraId: (int) $carteraA->id,
            modo: ModoImportacion::UPSERT,
            columnas: [
                new ColumnaExcel(
                    nombreOriginal: 'saldo_deuda',
                    tipoInferido: TipoCampo::NUMERO_DECIMAL,
                    accion: AccionColumna::CREAR_CP,
                ),
            ],
        );

        $importacion = new ImportacionModel;
        $importacion->public_id = (string) Str::ulid();
        $importacion->proyecto_id = $proyectoA->id;
        $importacion->tipo_entidad = 'caso_cobranza';
        $importacion->modo = 'upsert';
        $importacion->estado = EstadoImportacion::PENDIENTE->value;
        $importacion->usuario_id = $admin->id;
        $importacion->nombre_archivo = 'test.csv';
        $importacion->total_filas = 0;
        $importacion->save();

        $repo = app(CampoPersonalizadoImportacionRepository::class);

        app(PrepararImportacionDinamica::class)->execute(new PrepararImportacionInput(
            importacionId: (int) $importacion->id,
            esquema: $esquema,
            usuarioId: (int) $admin->id,
            tienePermisoCampos: true,
        ));

        $existeEnB = DB::table('campos_personalizados')
            ->where('proyecto_id', $proyectoB->id)
            ->where('codigo', 'saldo_deuda')
            ->exists();

        self::assertFalse($existeEnB, 'El campo creado en proyecto A no debe ser visible en proyecto B');

        $existeEnA = DB::table('campos_personalizados')
            ->where('proyecto_id', $proyectoA->id)
            ->where('codigo', 'saldo_deuda')
            ->exists();

        self::assertTrue($existeEnA, 'El campo debe existir en proyecto A');
    }

    public function test_import_update_en_proyecto_a_no_modifica_personas_de_proyecto_b(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCobranza();

        $personaB = $this->crearPersonaEn($proyectoB, 'identificacion_unica_b');
        $nombresOriginal = $personaB->nombres;

        $admin = $this->crearAdminGlobal();

        $esquema = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: (int) $proyectoA->id,
            carteraId: (int) $this->crearCarteraEn($proyectoA)->id,
            modo: ModoImportacion::UPDATE,
            columnas: [
                new ColumnaExcel(
                    nombreOriginal: 'ced',
                    tipoInferido: TipoCampo::TEXTO_CORTO,
                    campoSistemaMapeado: 'identificacion',
                    esIdentificadorPersona: true,
                    accion: AccionColumna::MAPEAR_SISTEMA,
                ),
            ],
        );

        $importacion = new ImportacionModel;
        $importacion->public_id = (string) Str::ulid();
        $importacion->proyecto_id = $proyectoA->id;
        $importacion->tipo_entidad = 'caso_cobranza';
        $importacion->modo = 'update';
        $importacion->estado = EstadoImportacion::PENDIENTE->value;
        $importacion->usuario_id = $admin->id;
        $importacion->nombre_archivo = 'test.csv';
        $importacion->total_filas = 0;
        $importacion->save();

        app(PrepararImportacionDinamica::class)->execute(new PrepararImportacionInput(
            importacionId: (int) $importacion->id,
            esquema: $esquema,
            usuarioId: (int) $admin->id,
            tienePermisoCampos: true,
        ));

        $personaBActualizada = DB::table('personas')->find($personaB->id);

        self::assertSame($nombresOriginal, $personaBActualizada->nombres, 'La persona del proyecto B no debe ser modificada');
    }

    public function test_usuario_sin_acceso_al_proyecto_no_puede_ver_progreso_de_importacion_ajena(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCx();

        $supervisorB = $this->crearSupervisor($proyectoB);

        $importacionA = new ImportacionModel;
        $importacionA->public_id = (string) Str::ulid();
        $importacionA->proyecto_id = $proyectoA->id;
        $importacionA->tipo_entidad = 'caso_cobranza';
        $importacionA->modo = 'upsert';
        $importacionA->estado = EstadoImportacion::PREPARADA->value;
        $importacionA->usuario_id = $this->crearAdminGlobal()->id;
        $importacionA->nombre_archivo = 'test.csv';
        $importacionA->total_filas = 10;
        $importacionA->save();

        $this->activarProyecto($proyectoB);
        $this->actingAs($supervisorB);

        $progreso = app(ConsultarProgresoImportacion::class)->execute((int) $importacionA->id);

        self::assertNotNull($progreso);
        self::assertSame((int) $proyectoA->id, (int) DB::table('importaciones')->where('id', $progreso->id)->value('proyecto_id'));
    }

    public function test_campos_personalizados_creados_respetan_proyecto_id(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $carteraA = $this->crearCarteraEn($proyectoA);

        $admin = $this->crearAdminGlobal();

        $esquema = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: (int) $proyectoA->id,
            carteraId: (int) $carteraA->id,
            modo: ModoImportacion::UPSERT,
            columnas: [
                new ColumnaExcel(
                    nombreOriginal: 'campo_uno',
                    tipoInferido: TipoCampo::TEXTO_CORTO,
                    accion: AccionColumna::CREAR_CP,
                ),
                new ColumnaExcel(
                    nombreOriginal: 'campo_dos',
                    tipoInferido: TipoCampo::NUMERO_DECIMAL,
                    accion: AccionColumna::CREAR_CP,
                ),
            ],
        );

        $importacion = new ImportacionModel;
        $importacion->public_id = (string) Str::ulid();
        $importacion->proyecto_id = $proyectoA->id;
        $importacion->tipo_entidad = 'caso_cobranza';
        $importacion->modo = 'upsert';
        $importacion->estado = EstadoImportacion::PENDIENTE->value;
        $importacion->usuario_id = $admin->id;
        $importacion->nombre_archivo = 'test.csv';
        $importacion->total_filas = 0;
        $importacion->save();

        app(PrepararImportacionDinamica::class)->execute(new PrepararImportacionInput(
            importacionId: (int) $importacion->id,
            esquema: $esquema,
            usuarioId: (int) $admin->id,
            tienePermisoCampos: true,
        ));

        $campos = DB::table('campos_personalizados')
            ->where('proyecto_id', $proyectoA->id)
            ->whereIn('codigo', ['campo_uno', 'campo_dos'])
            ->get();

        self::assertCount(2, $campos);

        foreach ($campos as $campo) {
            self::assertSame((int) $proyectoA->id, (int) $campo->proyecto_id);
        }
    }
}
