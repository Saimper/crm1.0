<?php

declare(strict_types=1);

namespace App\Modules\Casos\Infrastructure\Http\Controllers;

use App\Models\User;
use App\Modules\Casos\Application\Services\ExportadorCsvHistorico;
use App\Support\Http\ParametroDeConsulta;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class ExportarHistoricoController
{
    public function __construct(private ExportadorCsvHistorico $exportador) {}

    public function __invoke(Request $request, int $proyecto_id): StreamedResponse
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof User && $usuario->tienePermiso('historico.ver', $proyecto_id)
            && $usuario->tienePermiso('historico.exportar', $proyecto_id), 403);
        $tipo = ParametroDeConsulta::texto($request, 'tipo') ?: 'cuentas';
        abort_unless(in_array($tipo, ['cuentas', 'gestiones', 'compromisos'], true), 422);

        $consulta = $usuario->carterasPermitidasParaPermiso('historico.ver', $proyecto_id);
        $exportacion = $usuario->carterasPermitidasParaPermiso('historico.exportar', $proyecto_id);
        $carteras = $consulta === null ? $exportacion : ($exportacion === null ? $consulta : array_values(array_intersect($consulta, $exportacion)));

        return $this->exportador->responder($proyecto_id, $carteras, $tipo, [
            'q' => ParametroDeConsulta::texto($request, 'q'),
            'cartera' => ParametroDeConsulta::texto($request, 'cartera'),
            'caso' => ParametroDeConsulta::texto($request, 'caso'),
            'archivo' => ParametroDeConsulta::texto($request, 'archivo'),
        ]);
    }
}
