<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Http\Controllers;

use App\Modules\Reportes\Application\Hidratacion\HidratadorDefinicionReporte;
use App\Modules\Reportes\Application\UseCases\EjecutarReporte;
use App\Modules\Reportes\Application\UseCases\RegistrarEjecucionReporte;
use App\Modules\Reportes\Domain\Constructor\Contracts\RepositorioDefinicionReporte;
use App\Modules\Reportes\Infrastructure\Http\Streamers\StreamerReporteCsv;
use App\Modules\Reportes\Infrastructure\Http\Streamers\StreamerReporteXlsx;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportarReporteController
{
    public function __construct(
        private readonly RepositorioDefinicionReporte $repositorio,
        private readonly EjecutarReporte $ejecutar,
        private readonly RegistrarEjecucionReporte $registrar,
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
        $resultado = $this->ejecutar->execute($def);
        $usuarioId = (int) auth()->id();

        $onComplete = function (int $total) use ($definicion_id, $proyecto_id, $usuarioId, $formato, $inicio): void {
            $duracion = (int) ((microtime(true) * 1000) - $inicio);
            $this->registrar->execute($definicion_id, $proyecto_id, $usuarioId, $formato, $total, $duracion);
        };

        return $formato === 'xlsx'
            ? $this->streamerXlsx->stream($resultado, $filename, $onComplete)
            : $this->streamerCsv->stream($resultado, $filename, $onComplete);
    }
}
