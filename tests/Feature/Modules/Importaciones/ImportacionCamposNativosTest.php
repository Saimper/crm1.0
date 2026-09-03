<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Importaciones\Application\UseCases\EjecutarImportacionDinamica;
use App\Modules\Importaciones\Application\UseCases\EjecutarImportacionInput;
use App\Modules\Importaciones\Application\UseCases\InferirEsquemaDesdeHeaders;
use App\Modules\Importaciones\Application\UseCases\InferirEsquemaInput;
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

/**
 * F41: reproduce el fallo reportado por el cliente sobre la base del Banco Azteca.
 *
 * El archivo traía el nombre y el saldo, la importación decía haber procesado las
 * filas, pero Personas mostraba «—» y el caso quedaba sin saldo: todo se había
 * guardado como Campo Personalizado en vez de en la columna nativa.
 */
final class ImportacionCamposNativosTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    /** Encabezados reales de la base del cliente */
    private const HEADERS = ['CUENTA', 'CEDULA', 'NOMBRE DEL TITULAR', 'SALDO TOTAL', 'CAPITAL', 'DIAS EN ATRASO', 'SEGMENTO'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_el_nombre_y_los_saldos_se_guardan_en_columnas_nativas(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $this->crearEstadoCasoEn($proyecto, 'ACTIVO');
        $admin = $this->crearAdminGlobal();

        $this->ejecutarImportacion($proyecto, $cartera, $admin, [
            [
                'identificacion' => '8-990-429',
                'nombres' => 'ALEXIS SANTOS',
                'saldo_total' => '1,250.40',
                'saldo_capital' => '1000.00',
                'dias_mora' => '45',
                'segmento' => 'PREMIUM',
                'id_cpelegido' => '100122333',
            ],
        ]);

        $persona = DB::table('personas')
            ->where('proyecto_id', $proyecto->id)
            ->where('identificacion', '8-990-429')
            ->first();

        self::assertNotNull($persona);
        self::assertSame('ALEXIS SANTOS', $persona->nombres, 'El nombre debe quedar en personas.nombres.');

        $cti = DB::table('casos_cobranza')
            ->where('proyecto_id', $proyecto->id)
            ->where('numero_prestamo', '100122333')
            ->first();

        self::assertNotNull($cti);
        self::assertSame('1250.40', (string) $cti->saldo_total, 'El separador de miles no debe truncar el saldo.');
        self::assertSame('1000.00', (string) $cti->saldo_capital);
        self::assertSame(45, (int) $cti->dias_mora);
    }

    public function test_la_columna_ajena_al_nucleo_sigue_yendo_a_campos_personalizados(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $this->crearEstadoCasoEn($proyecto, 'ACTIVO');
        $admin = $this->crearAdminGlobal();

        $this->ejecutarImportacion($proyecto, $cartera, $admin, [
            [
                'identificacion' => '8-111-222',
                'nombres' => 'MARIA PEREZ',
                'saldo_total' => '500',
                'saldo_capital' => '400',
                'dias_mora' => '10',
                'segmento' => 'MASIVO',
                'id_cpelegido' => '100999888',
            ],
        ]);

        $campo = DB::table('campos_personalizados')
            ->where('proyecto_id', $proyecto->id)
            ->where('codigo', 'segmento')
            ->first();

        self::assertNotNull($campo, 'Segmento no tiene columna nativa: debe seguir siendo campo personalizado.');

        $valor = DB::table('valores_campo_personalizado')
            ->where('campo_personalizado_id', $campo->id)
            ->value('valor_texto_corto');

        self::assertSame('MASIVO', $valor);
    }

    public function test_dias_de_atraso_negativos_no_rompen_la_carga(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $this->crearEstadoCasoEn($proyecto, 'ACTIVO');
        $admin = $this->crearAdminGlobal();

        // La base del cliente trae días negativos: son días por vencer, no mora.
        // dias_mora es int unsigned, así que el valor se descarta en vez de fallar.
        $this->ejecutarImportacion($proyecto, $cartera, $admin, [
            [
                'identificacion' => '8-333-444',
                'nombres' => 'CARLOS DIAZ',
                'saldo_total' => '900.00',
                'saldo_capital' => '900.00',
                'dias_mora' => '-147',
                'segmento' => 'MASIVO',
                'id_cpelegido' => '100777666',
            ],
        ]);

        $cti = DB::table('casos_cobranza')
            ->where('proyecto_id', $proyecto->id)
            ->where('numero_prestamo', '100777666')
            ->first();

        self::assertNotNull($cti, 'La fila debe cargarse igual.');
        self::assertNull($cti->dias_mora);
        self::assertSame('900.00', (string) $cti->saldo_total, 'El resto de la fila sí se guarda.');
    }

    public function test_la_inferencia_reconoce_sola_las_columnas_del_archivo_del_cliente(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);

        $salida = app(InferirEsquemaDesdeHeaders::class)->execute(new InferirEsquemaInput(
            headers: self::HEADERS,
            filasMuestra: [[
                'CUENTA' => '100122333',
                'CEDULA' => '8-990-429',
                'NOMBRE DEL TITULAR' => 'ALEXIS SANTOS',
                'SALDO TOTAL' => '1250.40',
                'CAPITAL' => '1000.00',
                'DIAS EN ATRASO' => '45',
                'SEGMENTO' => 'PREMIUM',
            ]],
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: (int) $proyecto->id,
            carteraId: (int) $cartera->id,
        ));

        $mapeadas = [];
        foreach ($salida->columnas as $columna) {
            if ($columna->accion === AccionColumna::MAPEAR_SISTEMA) {
                $mapeadas[$columna->nombreOriginal] = $columna->campoSistemaMapeado;
            }
        }

        self::assertSame('identificacion', $mapeadas['CEDULA'] ?? null);
        self::assertSame('nombres', $mapeadas['NOMBRE DEL TITULAR'] ?? null, 'El cliente no debería mapear el nombre a mano.');
        self::assertSame('saldo_total', $mapeadas['SALDO TOTAL'] ?? null);
        self::assertSame('saldo_capital', $mapeadas['CAPITAL'] ?? null);
        self::assertSame('dias_mora', $mapeadas['DIAS EN ATRASO'] ?? null);
        self::assertArrayNotHasKey('SEGMENTO', $mapeadas);
    }

    public function test_dos_columnas_no_pueden_reclamar_el_mismo_campo(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);

        $salida = app(InferirEsquemaDesdeHeaders::class)->execute(new InferirEsquemaInput(
            headers: ['CEDULA', 'NOMBRE', 'NOMBRE DEL TITULAR'],
            filasMuestra: [['CEDULA' => '8-1-1', 'NOMBRE' => 'A', 'NOMBRE DEL TITULAR' => 'B']],
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: (int) $proyecto->id,
            carteraId: (int) $cartera->id,
        ));

        $aNombres = array_filter(
            $salida->columnas,
            static fn (ColumnaExcel $c): bool => $c->campoSistemaMapeado === 'nombres',
        );

        self::assertCount(1, $aNombres, 'Solo una columna puede alimentar «nombres».');
        self::assertNotEmpty($salida->advertencias);
    }

    public function test_una_columna_repetitiva_no_crea_un_campo_de_seleccion_sin_opciones(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $this->crearEstadoCasoEn($proyecto, 'ACTIVO');
        $admin = $this->crearAdminGlobal();

        // La inferencia propone selección única cuando una columna tiene pocos valores
        // distintos, pero la importación no define catálogos de opciones: el campo se
        // crea como texto. Antes se creaba como selección y el guardado casteaba la
        // etiqueta a int, violando la FK contra opciones_campo_personalizado.
        $esquema = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: (int) $proyecto->id,
            carteraId: (int) $cartera->id,
            modo: ModoImportacion::UPSERT,
            columnas: [
                new ColumnaExcel('CEDULA', TipoCampo::TEXTO_CORTO, 'identificacion', true, false, AccionColumna::MAPEAR_SISTEMA),
                new ColumnaExcel('CUENTA', TipoCampo::TEXTO_CORTO, null, false, true, AccionColumna::CREAR_CP),
                new ColumnaExcel('SEGMENTO', TipoCampo::SELECCION_UNICA, null, false, false, AccionColumna::CREAR_CP),
            ],
        );

        $importacion = new ImportacionModel;
        $importacion->public_id = (string) Str::ulid();
        $importacion->proyecto_id = $proyecto->id;
        $importacion->tipo_entidad = 'caso_cobranza';
        $importacion->modo = 'upsert';
        $importacion->estado = EstadoImportacion::PENDIENTE->value;
        $importacion->usuario_id = $admin->id;
        $importacion->nombre_archivo = 'base azteca.xlsx';
        $importacion->total_filas = 1;
        $importacion->save();

        ImportacionFilaModel::query()->create([
            'importacion_id' => $importacion->id,
            'proyecto_id' => $proyecto->id,
            'numero_fila' => 1,
            'estado' => 'pendiente',
            'payload' => ['identificacion' => '8-1-1', 'cuenta' => '900', 'segmento' => 'SEGMENTO 14 - 25', 'id_cpelegido' => '900'],
        ]);

        app(PrepararImportacionDinamica::class)->execute(new PrepararImportacionInput(
            importacionId: (int) $importacion->id,
            esquema: $esquema,
            usuarioId: (int) $admin->id,
            tienePermisoCampos: true,
        ));

        $tipo = DB::table('campos_personalizados')
            ->where('proyecto_id', $proyecto->id)
            ->where('codigo', 'segmento')
            ->value('tipo');

        self::assertSame('texto_corto', $tipo);

        $resultado = app(EjecutarImportacionDinamica::class)->execute(new EjecutarImportacionInput(
            importacionId: (int) $importacion->id,
            chunkSize: 100,
        ));

        self::assertSame(0, $resultado['invalidas'], 'La fila no debe fallar por el campo repetitivo.');
    }

    /**
     * @param  list<array<string, string>>  $filas
     */
    private function ejecutarImportacion(object $proyecto, object $cartera, object $admin, array $filas): void
    {
        $esquema = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: (int) $proyecto->id,
            carteraId: (int) $cartera->id,
            modo: ModoImportacion::UPSERT,
            columnas: $this->columnas(),
        );

        $importacion = new ImportacionModel;
        $importacion->public_id = (string) Str::ulid();
        $importacion->proyecto_id = $proyecto->id;
        $importacion->tipo_entidad = 'caso_cobranza';
        $importacion->modo = 'upsert';
        $importacion->estado = EstadoImportacion::PENDIENTE->value;
        $importacion->usuario_id = $admin->id;
        $importacion->nombre_archivo = 'base azteca.xlsx';
        $importacion->total_filas = count($filas);
        $importacion->save();

        foreach ($filas as $i => $fila) {
            ImportacionFilaModel::query()->create([
                'importacion_id' => $importacion->id,
                'proyecto_id' => $proyecto->id,
                'numero_fila' => $i + 1,
                'estado' => 'pendiente',
                'payload' => $fila,
            ]);
        }

        app(PrepararImportacionDinamica::class)->execute(new PrepararImportacionInput(
            importacionId: (int) $importacion->id,
            esquema: $esquema,
            usuarioId: (int) $admin->id,
            tienePermisoCampos: true,
        ));

        app(EjecutarImportacionDinamica::class)->execute(new EjecutarImportacionInput(
            importacionId: (int) $importacion->id,
            chunkSize: 100,
        ));
    }

    /**
     * @return list<ColumnaExcel>
     */
    private function columnas(): array
    {
        return [
            new ColumnaExcel('CEDULA', TipoCampo::TEXTO_CORTO, 'identificacion', true, false, AccionColumna::MAPEAR_SISTEMA),
            new ColumnaExcel('CUENTA', TipoCampo::TEXTO_CORTO, null, false, true, AccionColumna::CREAR_CP),
            new ColumnaExcel('NOMBRE DEL TITULAR', TipoCampo::TEXTO_CORTO, 'nombres', false, false, AccionColumna::MAPEAR_SISTEMA),
            new ColumnaExcel('SALDO TOTAL', TipoCampo::NUMERO_DECIMAL, 'saldo_total', false, false, AccionColumna::MAPEAR_SISTEMA),
            new ColumnaExcel('CAPITAL', TipoCampo::NUMERO_DECIMAL, 'saldo_capital', false, false, AccionColumna::MAPEAR_SISTEMA),
            new ColumnaExcel('DIAS EN ATRASO', TipoCampo::NUMERO_ENTERO, 'dias_mora', false, false, AccionColumna::MAPEAR_SISTEMA),
            new ColumnaExcel('SEGMENTO', TipoCampo::TEXTO_CORTO, null, false, false, AccionColumna::CREAR_CP),
        ];
    }
}
