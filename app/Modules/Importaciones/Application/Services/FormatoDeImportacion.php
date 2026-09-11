<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\Services;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Importaciones\Domain\ValueObjects\EsquemaImportacion;
use App\Modules\Tenancy\Domain\Contracts\RegionalConfiguration;
use App\Modules\Tenancy\Domain\ValueObjects\RegionalSettings;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final readonly class FormatoDeImportacion
{
    public function __construct(private RegionalConfiguration $regional) {}

    public function para(EsquemaImportacion $esquema): RegionalSettings
    {
        $regional = $this->regional->forProject($esquema->proyectoId);

        return match ($esquema->formatoEntrada) {
            'proyecto' => $regional,
            'estandar' => new RegionalSettings(timezone: $regional->timezone, currency: $regional->currency,
                dateFormat: 'Y-m-d', decimalPlaces: $regional->decimalPlaces, decimalSeparator: '.', thousandsSeparator: ',', weekStartsOn: $regional->weekStartsOn),
            default => throw new InvalidArgumentException('Selecciona un formato de entrada válido.'),
        };
    }

    /**
     * @param  Collection<int, object>  $filas
     * @return list<array{fila: int, campo: string, entrada: string, interpretado: string}>
     */
    public function vistaPrevia(Collection $filas, ?string $esquemaJson, string $formatoEntrada): array
    {
        if ($esquemaJson === null) {
            return [];
        }
        $original = EsquemaImportacion::deserializar($esquemaJson);
        $esquema = new EsquemaImportacion($original->target, $original->proyectoId, $original->carteraId,
            $original->modo, $original->columnas, formatoEntrada: $formatoEntrada);
        $formato = $this->para($esquema);
        $vista = [];
        foreach ($filas->take(3) as $fila) {
            $payload = is_string($fila->payload) ? json_decode($fila->payload, true) : (array) $fila->payload;
            foreach ($esquema->columnas as $columna) {
                $campo = $columna->campoSistemaMapeado ?? $columna->codigoSugerido();
                $valor = (string) ($payload[$columna->clavePayload()] ?? '');
                $fecha = str_starts_with($campo, 'fecha_') || in_array($columna->tipoInferido, [TipoCampo::FECHA, TipoCampo::FECHA_HORA], true);
                $numero = in_array($campo, ['saldo_total', 'saldo_capital', 'saldo_interes', 'monto_original', 'cuota_mensual', 'valor_estimado_monto'], true)
                    || in_array($columna->tipoInferido, [TipoCampo::NUMERO_DECIMAL, TipoCampo::MONEDA], true);
                if ($valor === '' || (! $fecha && ! $numero)) {
                    continue;
                }
                try {
                    $interpretado = $fecha ? $formato->parseDate($valor, str_contains($valor, ':'))->format('Y-m-d H:i:s') : $formato->parseNumber($valor);
                } catch (InvalidArgumentException $e) {
                    $interpretado = $e->getMessage();
                }
                $vista[] = ['fila' => (int) $fila->numero_fila, 'campo' => $columna->nombreOriginal, 'entrada' => $valor, 'interpretado' => $interpretado];
            }
        }

        return $vista;
    }
}
