<?php

declare(strict_types=1);

namespace App\Modules\CamposPersonalizados\Application\Services;

use App\Modules\CamposPersonalizados\Domain\Services\EvaluadorReglas;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\AmbitoCampo;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\CamposPersonalizados\Infrastructure\Persistence\Models\CampoPersonalizadoModel;
use App\Modules\CamposPersonalizados\Infrastructure\Persistence\Models\ValorCampoPersonalizadoModel;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

/**
 * Servicio de alto nivel para leer y guardar valores de campos personalizados.
 * Encapsula el mapeo valor ↔ columna por tipo (§7.3 CLAUDE.md v2).
 */
final readonly class ServicioCamposPersonalizados
{
    public function __construct(
        private EvaluadorReglas $evaluador,
        private ConnectionInterface $db,
    ) {}

    /**
     * Devuelve los campos personalizados aplicables al ámbito dado (activos, ordenados).
     *
     * @return Collection<int, CampoPersonalizadoModel>
     */
    public function campos(int $proyectoId, AmbitoCampo $ambito, int $ambitoId): Collection
    {
        return CampoPersonalizadoModel::query()
            ->sinScopeProyecto()
            ->where('proyecto_id', $proyectoId)
            ->where('ambito', $ambito->value)
            ->where('ambito_id', $ambitoId)
            ->where('activo', true)
            ->orderBy('orden')
            ->get();
    }

    /**
     * Valida y persiste los valores para una entidad (caso/gestión/compromiso).
     *
     * @param  array<string, mixed>  $valoresPorCodigo  [codigo_campo => valor]
     */
    public function guardarValores(
        int $proyectoId,
        AmbitoCampo $ambito,
        int $ambitoId,
        int $entidadId,
        array $valoresPorCodigo,
    ): void {
        $campos = $this->campos($proyectoId, $ambito, $ambitoId);

        // 1) Validar todos antes de persistir nada.
        foreach ($campos as $campo) {
            $valor = $valoresPorCodigo[$campo->codigo] ?? null;
            $this->evaluador->validar(
                TipoCampo::from((string) $campo->tipo),
                $valor,
                is_array($campo->reglas) ? $campo->reglas : [],
                (bool) $campo->obligatorio,
                (string) $campo->etiqueta,
            );
        }

        // 2) Persistir en transacción.
        $this->db->transaction(function () use ($campos, $valoresPorCodigo, $entidadId): void {
            foreach ($campos as $campo) {
                if (! array_key_exists($campo->codigo, $valoresPorCodigo)) {
                    continue;
                }
                $valor = $valoresPorCodigo[$campo->codigo];

                $payload = $this->mapearValorAColumna(TipoCampo::from((string) $campo->tipo), $valor);

                ValorCampoPersonalizadoModel::query()->updateOrCreate(
                    [
                        'campo_personalizado_id' => $campo->id,
                        'entidad_id' => $entidadId,
                    ],
                    $payload,
                );
            }
        });
    }

    /** @return array<string, mixed> */
    private function mapearValorAColumna(TipoCampo $tipo, mixed $valor): array
    {
        $columnas = array_fill_keys([
            'valor_texto_corto', 'valor_texto_largo',
            'valor_numero_entero', 'valor_numero_decimal',
            'valor_fecha', 'valor_fecha_hora',
            'valor_booleano', 'valor_opcion_id',
            'valor_opciones_ids', 'valor_moneda_monto', 'valor_moneda_codigo',
        ], null);

        if ($valor === null) {
            return $columnas;
        }

        match ($tipo) {
            TipoCampo::TEXTO_CORTO => $columnas['valor_texto_corto'] = (string) $valor,
            TipoCampo::TEXTO_LARGO => $columnas['valor_texto_largo'] = (string) $valor,
            TipoCampo::NUMERO_ENTERO => $columnas['valor_numero_entero'] = (int) $valor,
            TipoCampo::NUMERO_DECIMAL => $columnas['valor_numero_decimal'] = (string) $valor,
            TipoCampo::FECHA => $columnas['valor_fecha'] = (string) $valor,
            TipoCampo::FECHA_HORA => $columnas['valor_fecha_hora'] = (string) $valor,
            TipoCampo::BOOLEANO => $columnas['valor_booleano'] = (bool) $valor,
            TipoCampo::SELECCION_UNICA => $columnas['valor_opcion_id'] = (int) $valor,
            TipoCampo::SELECCION_MULTIPLE => $columnas['valor_opciones_ids'] = is_array($valor) ? $valor : [$valor],
            TipoCampo::MONEDA => [
                $columnas['valor_moneda_monto'] = is_array($valor) ? (string) ($valor['monto'] ?? '0') : (string) $valor,
                $columnas['valor_moneda_codigo'] = is_array($valor) ? (string) ($valor['moneda'] ?? 'USD') : 'USD',
            ],
        };

        return $columnas;
    }
}
