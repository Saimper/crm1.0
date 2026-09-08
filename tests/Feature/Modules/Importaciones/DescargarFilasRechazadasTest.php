<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use App\Models\User;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Importaciones\Domain\Enums\AccionColumna;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use App\Modules\Importaciones\Domain\ValueObjects\ColumnaExcel;
use App\Modules\Importaciones\Domain\ValueObjects\EsquemaImportacion;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Las filas rechazadas, como CSV con los nombres originales de las columnas
 * y, al final, `numero_fila`, `estado` y `motivo`: el supervisor corrige y
 * vuelve a subir con el mismo mapeo.
 */
final class DescargarFilasRechazadasTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_las_cabeceras_son_los_nombres_originales_mas_las_tres_finales(): void
    {
        [$proyecto, $supervisor, $publicId] = $this->importacionConFilas();

        $respuesta = $this->actingAs($supervisor)
            ->get(route('proyectos.importaciones.rechazadas', ['proyecto_id' => $proyecto->id, 'importacion' => $publicId]));

        $respuesta->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $lineas = $this->lineas($respuesta->streamedContent());

        $this->assertSame('CEDULA,CUENTA,NOMBRE,numero_fila,estado,motivo', $lineas[0], 'La columna ignorada no aparece.');
    }

    public function test_solo_salen_las_filas_rechazadas_con_su_motivo(): void
    {
        [$proyecto, $supervisor, $publicId] = $this->importacionConFilas();

        $lineas = $this->lineas($this->actingAs($supervisor)
            ->get(route('proyectos.importaciones.rechazadas', ['proyecto_id' => $proyecto->id, 'importacion' => $publicId]))
            ->streamedContent());

        $this->assertCount(4, $lineas, 'cabecera + invalida + duplicada + omitida; la procesada no');
        $this->assertSame('8-2-2,PR-2,Beto,2,invalida,"Los días de mora (99999) superan los 40 años."', $lineas[1]);
        $this->assertSame('8-3-3,PR-3,Caro,3,duplicada,"El caso ya existe en el proyecto"', $lineas[2]);
        $this->assertSame('8-4-4,PR-4,,4,omitida,"razón de omisión"', $lineas[3], 'Sin mensaje_error se usa razon_omision; la celda sin valor sale vacía.');
    }

    public function test_deja_huella_de_exportacion_en_la_auditoria(): void
    {
        [$proyecto, $supervisor, $publicId] = $this->importacionConFilas();

        $this->actingAs($supervisor)
            ->get(route('proyectos.importaciones.rechazadas', ['proyecto_id' => $proyecto->id, 'importacion' => $publicId]))
            ->streamedContent();

        $huella = DB::table('auditorias')
            ->where('proyecto_id', $proyecto->id)
            ->where('evento', 'exportado')
            ->where('entidad_tipo', 'importacion_filas')
            ->first();

        $this->assertNotNull($huella);
        $this->assertSame((int) $supervisor->id, (int) $huella->usuario_id);

        $cambios = json_decode((string) $huella->cambios, true);
        $this->assertSame($publicId, $cambios['filtros']['despues']['importacion']);
        $this->assertSame(3, $cambios['total_filas']['despues']);
    }

    public function test_sin_esquema_las_cabeceras_son_las_claves_del_payload(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);
        $publicId = (string) Str::ulid();
        $importacionId = $this->insertarImportacion($proyecto, $supervisor->id, $publicId, esquema: null);

        DB::table('importacion_filas')->insert([
            'importacion_id' => $importacionId,
            'proyecto_id' => $proyecto->id,
            'numero_fila' => 1,
            'estado' => 'invalida',
            'payload' => json_encode(['identificacion' => '8-1-1', 'nombres' => 'Ana'], JSON_THROW_ON_ERROR),
            'mensaje_error' => 'sin cartera',
        ]);

        $lineas = $this->lineas($this->actingAs($supervisor)
            ->get(route('proyectos.importaciones.rechazadas', ['proyecto_id' => $proyecto->id, 'importacion' => $publicId]))
            ->streamedContent());

        // MySQL reordena las claves de una columna JSON al guardarla, así que
        // el orden de las cabeceras no es el del archivo: se comprueba por nombre.
        $cabeceras = str_getcsv($lineas[0]);
        $fila = array_combine($cabeceras, str_getcsv($lineas[1]));

        $this->assertEqualsCanonicalizing(['identificacion', 'nombres', 'numero_fila', 'estado', 'motivo'], $cabeceras);
        $this->assertSame(['numero_fila', 'estado', 'motivo'], array_slice($cabeceras, -3), 'Las tres fijas van siempre al final.');
        $this->assertSame('8-1-1', $fila['identificacion']);
        $this->assertSame('Ana', $fila['nombres']);
        $this->assertSame('invalida', $fila['estado']);
        $this->assertSame('sin cartera', $fila['motivo']);
    }

    /** Multi-tenancy (§12): el ulid de otro proyecto responde como si no existiera. */
    public function test_la_importacion_de_otro_proyecto_responde_404(): void
    {
        [$proyectoA, $supervisorA] = $this->importacionConFilas();
        [, , $publicIdDeB] = $this->importacionConFilas();

        $this->actingAs($supervisorA)
            ->get(route('proyectos.importaciones.rechazadas', ['proyecto_id' => $proyectoA->id, 'importacion' => $publicIdDeB]))
            ->assertNotFound();
    }

    /**
     * Mientras el worker sigue, la lista de rechazadas crece por detrás: el
     * supervisor corregiría un archivo al que le faltan filas y volvería a
     * subir sólo una parte. La pantalla no ofrece el enlace antes de terminar;
     * esto cubre la URL escrita a mano o guardada de la vez anterior.
     */
    public function test_una_importacion_en_curso_no_se_descarga(): void
    {
        [$proyecto, $supervisor, $publicId] = $this->importacionConFilas();

        DB::table('importaciones')->where('public_id', $publicId)->update(['estado' => 'procesando']);

        $this->actingAs($supervisor)
            ->get(route('proyectos.importaciones.rechazadas', ['proyecto_id' => $proyecto->id, 'importacion' => $publicId]))
            ->assertStatus(409);

        DB::table('importaciones')->where('public_id', $publicId)->update(['estado' => 'cancelada']);

        $this->actingAs($supervisor)
            ->get(route('proyectos.importaciones.rechazadas', ['proyecto_id' => $proyecto->id, 'importacion' => $publicId]))
            ->assertOk();
    }

    public function test_un_gestor_no_puede_descargar(): void
    {
        [$proyecto, , $publicId] = $this->importacionConFilas();

        $this->actingAs($this->crearGestor($proyecto))
            ->get(route('proyectos.importaciones.rechazadas', ['proyecto_id' => $proyecto->id, 'importacion' => $publicId]))
            ->assertForbidden();
    }

    /**
     * Una importación con esquema persistido y cuatro filas: una procesada y
     * tres rechazadas de los tres tipos.
     *
     * @return array{0: stdClass, 1: User, 2: string}
     */
    private function importacionConFilas(): array
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
                new ColumnaExcel('CEDULA', TipoCampo::TEXTO_CORTO, 'identificacion', true, false, AccionColumna::MAPEAR_SISTEMA),
                new ColumnaExcel('CUENTA', TipoCampo::TEXTO_CORTO, null, false, true, AccionColumna::CREAR_CP),
                new ColumnaExcel('BASURA', TipoCampo::TEXTO_CORTO, null, false, false, AccionColumna::IGNORAR),
                new ColumnaExcel('NOMBRE', TipoCampo::TEXTO_CORTO, 'nombres', false, false, AccionColumna::MAPEAR_SISTEMA),
            ],
        );

        $publicId = (string) Str::ulid();
        $importacionId = $this->insertarImportacion($proyecto, $supervisor->id, $publicId, $esquema->serializar());

        $filas = [
            [1, 'procesada', ['identificacion' => '8-1-1', 'cuenta' => 'PR-1', 'id_cpelegido' => 'PR-1', 'nombres' => 'Ana'], null, null],
            [2, 'invalida', ['identificacion' => '8-2-2', 'cuenta' => 'PR-2', 'id_cpelegido' => 'PR-2', 'nombres' => 'Beto'], 'Los días de mora (99999) superan los 40 años.', null],
            [3, 'duplicada', ['identificacion' => '8-3-3', 'cuenta' => 'PR-3', 'id_cpelegido' => 'PR-3', 'nombres' => 'Caro'], 'El caso ya existe en el proyecto', null],
            [4, 'omitida', ['identificacion' => '8-4-4', 'cuenta' => 'PR-4', 'id_cpelegido' => 'PR-4'], null, 'razón de omisión'],
        ];

        foreach ($filas as [$numero, $estado, $payload, $mensaje, $razon]) {
            DB::table('importacion_filas')->insert([
                'importacion_id' => $importacionId,
                'proyecto_id' => $proyecto->id,
                'numero_fila' => $numero,
                'estado' => $estado,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'mensaje_error' => $mensaje,
                'razon_omision' => $razon,
            ]);
        }

        return [$proyecto, $supervisor, $publicId];
    }

    private function insertarImportacion(stdClass $proyecto, int $usuarioId, string $publicId, ?string $esquema): int
    {
        return (int) DB::table('importaciones')->insertGetId([
            'public_id' => $publicId,
            'proyecto_id' => $proyecto->id,
            'tipo_entidad' => 'caso_cobranza',
            'modo' => 'upsert',
            'estado' => 'completada',
            'usuario_id' => $usuarioId,
            'nombre_archivo' => 'base.csv',
            'total_filas' => 4,
            'procesadas' => 1,
            'invalidas' => 1,
            'duplicadas' => 1,
            'omitidas' => 1,
            'esquema' => $esquema,
        ]);
    }

    /** @return list<string> */
    private function lineas(string $csv): array
    {
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        return array_values(array_filter(explode("\n", trim(substr($csv, 3)))));
    }
}
