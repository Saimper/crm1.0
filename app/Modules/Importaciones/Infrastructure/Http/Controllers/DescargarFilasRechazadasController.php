<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Infrastructure\Http\Controllers;

use App\Models\User;
use App\Modules\Auditoria\Domain\Contracts\RegistroDeExportaciones;
use App\Modules\Importaciones\Domain\Enums\EstadoFila;
use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\ValueObjects\EsquemaImportacion;
use App\Support\Csv\RespuestaCsv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use stdClass;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Las filas que una importación rechazó, como CSV para corregir y volver a
 * subir.
 *
 * Las cabeceras son los nombres ORIGINALES de las columnas del archivo, en su
 * orden, y al final `numero_fila`, `estado` y `motivo`: así el supervisor
 * arregla las celdas en el mismo fichero y lo vuelve a subir con el mismo
 * mapeo, sin renombrar nada. El valor de cada columna sale del payload con la
 * clave que el wizard usó al construirlo (`ColumnaExcel::clavePayload`).
 *
 * Permiso `importaciones.crear`: quien pudo subir el archivo ya tenía su
 * contenido. No `importaciones.ver`, que hoy no abre ninguna puerta y daría a
 * entender que existe una lectura que no existe.
 *
 * La importación se busca por `public_id` Y por proyecto: un ulid de otro
 * proyecto responde 404, igual que uno inexistente, para no confirmar nada.
 *
 * Y sólo de importaciones terminadas (409 si no): mientras el worker sigue, la
 * lista de rechazadas crece por detrás y el supervisor corregiría un archivo
 * al que le faltan filas. La pantalla no ofrece el enlace antes; el 409 es
 * para la URL escrita a mano o guardada.
 *
 * Pasada la retención, el contenido del archivo se depura
 * (`importaciones:purgar-payloads`) y esto responde 410: el fichero saldría
 * con las columnas en blanco, que es peor que no salir, porque el supervisor
 * lo subiría de vuelta creyendo que corrige algo.
 */
final class DescargarFilasRechazadasController
{
    public function __construct(private readonly RegistroDeExportaciones $registro) {}

    public function __invoke(Request $request, int $proyecto_id, string $importacion): StreamedResponse
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);
        abort_unless($usuario->tienePermiso('importaciones.crear', $proyecto_id), 403);

        $fila = DB::table('importaciones')
            ->where('proyecto_id', $proyecto_id)
            ->where('public_id', $importacion)
            ->first(['id', 'public_id', 'esquema', 'estado', 'payload_purgado_en']);
        abort_if($fila === null, 404);
        abort_unless(EstadoImportacion::from((string) $fila->estado)->esTerminal(), 409);
        abort_unless($fila->payload_purgado_en === null, 410);

        $importacionId = (int) $fila->id;
        $publicId = (string) $fila->public_id;
        $estadosRechazados = [EstadoFila::INVALIDA->value, EstadoFila::DUPLICADA->value, EstadoFila::OMITIDA->value];

        $columnas = $this->columnasDelArchivo($fila, $proyecto_id, $importacionId, $estadosRechazados);

        $consulta = DB::table('importacion_filas as f')
            ->where('f.proyecto_id', $proyecto_id)
            ->where('f.importacion_id', $importacionId)
            ->whereIn('f.estado', $estadosRechazados)
            ->select(['f.id', 'f.numero_fila', 'f.estado', 'f.payload', 'f.mensaje_error', 'f.razon_omision']);

        $cabeceras = array_merge(array_column($columnas, 'cabecera'), ['numero_fila', 'estado', 'motivo']);
        $claves = array_column($columnas, 'clave');

        return RespuestaCsv::desdeConsulta(
            'rechazadas_'.$publicId.'.csv',
            $cabeceras,
            $consulta,
            'f.id',
            'id',
            static function (stdClass $f) use ($claves): array {
                $payload = json_decode((string) $f->payload, true);
                $payload = is_array($payload) ? $payload : [];

                $celdas = [];
                foreach ($claves as $clave) {
                    $celdas[] = $payload[$clave] ?? '';
                }

                $celdas[] = (int) $f->numero_fila;
                $celdas[] = (string) $f->estado;
                $celdas[] = (string) ($f->mensaje_error ?? $f->razon_omision ?? '');

                return $celdas;
            },
            null,
            fn (int $total, bool $completa = true) => $this->registro->registrar(
                'importacion_filas',
                ['importacion' => $publicId],
                $total,
                $proyecto_id,
                null,
                (int) $usuario->id,
                $completa,
            ),
        );
    }

    /**
     * Cabecera y clave de payload de cada columna del archivo.
     *
     * Con esquema persistido: sus columnas no ignoradas, en orden. Sin esquema
     * (importaciones anteriores al wizard dinámico) no hay nombres originales
     * que devolver: se usan las claves del payload de la primera fila rechazada.
     *
     * @param  list<string>  $estadosRechazados
     * @return list<array{cabecera: string, clave: string}>
     */
    private function columnasDelArchivo(stdClass $importacion, int $proyectoId, int $importacionId, array $estadosRechazados): array
    {
        if ($importacion->esquema !== null) {
            try {
                $esquema = EsquemaImportacion::deserializar((string) $importacion->esquema);

                return array_map(
                    static fn ($columna): array => ['cabecera' => $columna->nombreOriginal, 'clave' => $columna->clavePayload()],
                    $esquema->columnasDelArchivo(),
                );
            } catch (InvalidArgumentException) {
                // Un esquema ilegible no debe impedir descargar lo rechazado:
                // se cae al mismo camino que las importaciones sin esquema.
            }
        }

        $primera = DB::table('importacion_filas')
            ->where('proyecto_id', $proyectoId)
            ->where('importacion_id', $importacionId)
            ->whereIn('estado', $estadosRechazados)
            ->orderBy('id')
            ->value('payload');

        $payload = $primera === null ? [] : json_decode((string) $primera, true);
        if (! is_array($payload)) {
            return [];
        }

        return array_map(
            static fn (string $clave): array => ['cabecera' => $clave, 'clave' => $clave],
            array_map('strval', array_keys($payload)),
        );
    }
}
