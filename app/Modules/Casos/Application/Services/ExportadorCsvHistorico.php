<?php

declare(strict_types=1);

namespace App\Modules\Casos\Application\Services;

use App\Modules\Auditoria\Domain\Contracts\RegistroDeExportaciones;
use App\Support\Csv\RespuestaCsv;
use Illuminate\Database\ConnectionInterface;
use stdClass;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class ExportadorCsvHistorico
{
    public function __construct(
        private ConsultaHistorico $consulta,
        private ConnectionInterface $db,
        private RegistroDeExportaciones $registro,
    ) {}

    /**
     * @param  list<int>|null  $carteras
     * @param  array{q: string, cartera: string, caso: string, archivo: string}  $filtros
     */
    public function responder(int $proyectoId, ?array $carteras, string $tipo, array $filtros): StreamedResponse
    {
        $base = $this->consulta->cuentas($proyectoId, $carteras, $filtros['q'], $filtros['cartera'], $filtros['caso'], $filtros['archivo']);
        if ($filtros['caso'] !== '' && $filtros['archivo'] === '') {
            $base->whereNull('h.archivo');
        }
        $query = match ($tipo) {
            'gestiones' => $this->consulta->gestiones($base, $proyectoId),
            'compromisos' => $this->consulta->compromisos($base, $proyectoId),
            default => $base,
        };
        $cabeceras = ['cuenta', 'cartera_historica', 'identificacion', 'persona', 'asesor_original', 'retirada_en'];
        $cabeceras = array_merge($cabeceras, match ($tipo) {
            'gestiones' => ['gestion', 'registrada_en', 'autor', 'canal', 'tipo', 'resultado', 'notas', 'duracion_segundos'],
            'compromisos' => ['compromiso', 'registrado_en', 'autor', 'tipo', 'estado_actual', 'vencimiento', 'resolucion', 'moneda', 'monto', 'detalle'],
            default => ['estado', 'motivo', 'moneda', 'saldo', 'dias_mora'],
        });
        $cursor = $tipo === 'cuentas' ? 'cursor_id' : 'export_id';
        $export = $this->db->table('casos')->fromSub($query, 'exportacion')->select('exportacion.*');

        return RespuestaCsv::desdeConsulta(
            nombreFichero: 'historico_'.$tipo.'_'.$proyectoId.'_'.date('Ymd_His').'.csv',
            cabeceras: $cabeceras,
            consulta: $export,
            columnaId: 'exportacion.'.$cursor,
            aliasId: $cursor,
            fila: fn (stdClass $row): array => $this->fila($row, $tipo, $proyectoId),
            alTerminar: fn (int $total, bool $completa = true) => $this->registro->registrar(
                $tipo === 'cuentas' ? 'casos' : $tipo,
                array_merge($filtros, ['historico' => true, 'tipo' => $tipo]),
                $total,
                $proyectoId,
                completa: $completa,
            ),
        );
    }

    /** @return list<mixed> */
    private function fila(stdClass $r, string $tipo, int $proyectoId): array
    {
        $base = [$r->referencia, $r->cartera_nombre, $r->identificacion,
            trim(($r->nombres ?? '').' '.($r->apellidos ?? '')) ?: $r->razon_social,
            $r->asesor_nombre, hora_local($r->fecha_archivo, null, $proyectoId)];

        return array_merge($base, match ($tipo) {
            'gestiones' => [$r->gestion_public_id, hora_local($r->creada_en, null, $proyectoId), $r->autor_nombre,
                $r->canal_nombre, $r->tipo_nombre, $r->resultado_nombre, $r->notas, $r->duracion_segundos],
            'compromisos' => [$r->compromiso_public_id, hora_local($r->creada_en, null, $proyectoId), $r->autor_nombre,
                $r->tipo_compromiso, $r->estado, fecha_local($r->fecha_vencimiento, $proyectoId),
                $r->fecha_resolucion ? fecha_local($r->fecha_resolucion, $proyectoId) : '', $r->moneda_compromiso,
                $r->monto_compromiso === null ? '' : numero_local($r->monto_compromiso, null, $proyectoId), $r->detalle],
            default => [$r->estado_nombre, $r->motivo, $r->moneda,
                $r->saldo_total === null ? '' : numero_local($r->saldo_total, null, $proyectoId), $r->dias_mora],
        });
    }
}
