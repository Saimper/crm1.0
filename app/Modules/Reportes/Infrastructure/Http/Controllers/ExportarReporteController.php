<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Http\Controllers;

use App\Models\User;
use App\Modules\Auditoria\Domain\Contracts\RegistroDeExportaciones;
use App\Modules\Reportes\Application\Hidratacion\HidratadorDefinicionReporte;
use App\Modules\Reportes\Application\UseCases\EjecutarReporte;
use App\Modules\Reportes\Application\UseCases\RegistrarEjecucionReporte;
use App\Modules\Reportes\Domain\Constructor\Contracts\RepositorioDefinicionReporte;
use App\Modules\Reportes\Infrastructure\Http\Streamers\StreamerReporteCsv;
use App\Modules\Reportes\Infrastructure\Http\Streamers\StreamerReporteXlsx;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga un reporte del constructor (F32) en CSV o XLSX.
 *
 * Deja DOS rastros y no uno. `reportes_ejecuciones` es la métrica del módulo
 * —qué definición se corrió, cuánto tardó—, y la auditoría del cliente es
 * quién sacó sus datos: se escribe con el mismo evento `exportado` que las
 * cuatro descargas de listado, atribuida a la entidad raíz del reporte, que es
 * la tabla de la que salieron las filas. Sin esto, la extracción más potente
 * de la aplicación era la única que no dejaba huella donde el cliente mira.
 */
final class ExportarReporteController
{
    public function __construct(
        private readonly RepositorioDefinicionReporte $repositorio,
        private readonly EjecutarReporte $ejecutar,
        private readonly RegistrarEjecucionReporte $registrar,
        private readonly RegistroDeExportaciones $huella,
        private readonly StreamerReporteCsv $streamerCsv,
        private readonly StreamerReporteXlsx $streamerXlsx,
    ) {}

    public function __invoke(Request $request, int $proyecto_id, int $definicion_id): StreamedResponse
    {
        abort_unless(
            auth()->user()?->tienePermiso('reportes.constructor.exportar', $proyecto_id) === true,
            403,
        );

        $data = $this->repositorio->buscar($definicion_id, $proyecto_id);
        abort_if($data === null, 404);

        $formato = strtolower((string) $request->query('formato', 'csv'));
        abort_unless(in_array($formato, ['csv', 'xlsx'], true), 422);

        $proyectoCodigo = (string) DB::table('proyectos')->where('id', $proyecto_id)->value('codigo');
        $filename = $data['codigo'].'_'.$proyectoCodigo.'_'.now()->format('Ymd_His').'.'.$formato;

        $def = HidratadorDefinicionReporte::desdeArray($data);

        $inicio = (int) (microtime(true) * 1000);
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);
        // El recorte por cartera del rol (F22) vale también aquí: un reporte es
        // una consulta que el usuario compone, y sin esto era la puerta de
        // atrás a las carteras que la bandeja le esconde.
        $resultado = $this->ejecutar->execute($def, null, $usuario->carterasPermitidas($proyecto_id));
        $usuarioId = (int) $usuario->id;

        $onComplete = function (int $total, bool $completa) use ($def, $data, $definicion_id, $proyecto_id, $usuarioId, $formato, $inicio): void {
            $duracion = (int) ((microtime(true) * 1000) - $inicio);
            $this->registrar->execute($definicion_id, $proyecto_id, $usuarioId, $formato, $total, $duracion);

            $this->huella->registrar(
                entidadTipo: $def->entidad->value,
                filtros: ['reporte' => (string) $data['codigo'], 'formato' => $formato],
                totalFilas: $total,
                proyectoId: $proyecto_id,
                usuarioId: $usuarioId,
                completa: $completa,
            );
        };

        return $formato === 'xlsx'
            ? $this->streamerXlsx->stream($resultado, $filename, $onComplete)
            : $this->streamerCsv->stream($resultado, $filename, $onComplete);
    }
}
