<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\Servicios;

use App\Modules\Reportes\Domain\Constructor\Enums\EntidadRaiz;
use App\Modules\Reportes\Domain\Constructor\Enums\TipoCampoReporte;
use App\Modules\Reportes\Domain\Constructor\ValueObjects\CampoDisponible;
use Illuminate\Support\Facades\DB;

/**
 * Resuelve campos personalizados §7 visibles por proyecto + entidad raíz.
 *
 * Solo expone tipos directamente reportables (textos/números/fechas/booleanos).
 * Selección única/múltiple/moneda quedan fuera del reporte por ahora — requieren
 * joins extra (opciones_campo_personalizado) que rompen la simplicidad del whitelist.
 */
final class ServicioCamposPersonalizadosReporte
{
    private const COLUMNA_VALOR = [
        'texto_corto' => 'valor_texto_corto',
        'texto_largo' => 'valor_texto_largo',
        'numero_entero' => 'valor_numero_entero',
        'numero_decimal' => 'valor_numero_decimal',
        'fecha' => 'valor_fecha',
        'fecha_hora' => 'valor_fecha_hora',
        'booleano' => 'valor_booleano',
    ];

    private const TIPO_REPORTE = [
        'texto_corto' => TipoCampoReporte::TEXTO,
        'texto_largo' => TipoCampoReporte::TEXTO,
        'numero_entero' => TipoCampoReporte::NUMERO,
        'numero_decimal' => TipoCampoReporte::DECIMAL,
        'fecha' => TipoCampoReporte::FECHA,
        'fecha_hora' => TipoCampoReporte::FECHA_HORA,
        'booleano' => TipoCampoReporte::BOOLEANO,
    ];

    /**
     * @return list<CampoDisponible>
     */
    public function obtenerCampos(EntidadRaiz $entidad, int $proyectoId): array
    {
        $ambito = $entidad->ambitoCampoPersonalizado();
        if ($ambito === null) {
            return [];
        }

        $rows = DB::table('campos_personalizados')
            ->where('proyecto_id', $proyectoId)
            ->where('ambito', $ambito)
            ->where('activo', true)
            ->whereIn('tipo', array_keys(self::COLUMNA_VALOR))
            ->orderBy('etiqueta')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $tipo = self::TIPO_REPORTE[$r->tipo] ?? null;
            $col = self::COLUMNA_VALOR[$r->tipo] ?? null;
            if ($tipo === null || $col === null) {
                continue;
            }
            $cpId = (int) $r->id;
            $alias = "vcp_{$cpId}";
            $out[] = new CampoDisponible(
                clave: "personalizado.{$cpId}_".$r->codigo,
                etiqueta: (string) $r->etiqueta,
                tipo: $tipo,
                sql: "{$alias}.{$col}",
                joinKey: "cp:{$cpId}",
                esCampoPersonalizado: true,
                campoPersonalizadoId: $cpId,
            );
        }

        return $out;
    }
}
