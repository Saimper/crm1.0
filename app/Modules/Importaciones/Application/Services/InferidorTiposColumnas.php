<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\Services;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;

/**
 * Analiza una muestra de valores de una columna y retorna el TipoCampo
 * más probable mediante heurísticas de tipo.
 */
final readonly class InferidorTiposColumnas
{
    /**
     * @param list<string> $valores
     */
    public function inferir(array $valores): TipoCampo
    {
        $valoresNoVacios = array_values(array_filter($valores, fn (string $v): bool => ! $this->esValorVacio($v)));

        if ($valoresNoVacios === []) {
            return TipoCampo::TEXTO_CORTO;
        }

        if ($this->todosBooleanos($valoresNoVacios)) {
            return TipoCampo::BOOLEANO;
        }

        if ($this->todosFechaHora($valoresNoVacios)) {
            return TipoCampo::FECHA_HORA;
        }

        if ($this->todosFecha($valoresNoVacios)) {
            return TipoCampo::FECHA;
        }

        if ($this->todosNumericos($valoresNoVacios)) {
            return $this->todosEnteros($valoresNoVacios)
                ? TipoCampo::NUMERO_ENTERO
                : TipoCampo::NUMERO_DECIMAL;
        }

        if (count($valoresNoVacios) >= 5 && $this->bajaCardinalidad($valoresNoVacios)) {
            return TipoCampo::SELECCION_UNICA;
        }

        if ($this->algunoLargo($valoresNoVacios)) {
            return TipoCampo::TEXTO_LARGO;
        }

        return TipoCampo::TEXTO_CORTO;
    }

    public function esValorVacio(mixed $valor): bool
    {
        if ($valor === null) {
            return true;
        }

        $s = trim((string) $valor);

        return $s === '' || strtolower($s) === 'null';
    }

    /**
     * @param list<string> $valores
     */
    private function todosBooleanos(array $valores): bool
    {
        $booleanos = ['true', 'false', '1', '0', 'si', 'sí', 'no', 'yes', 'no'];

        foreach ($valores as $v) {
            if (! in_array(strtolower(trim($v)), $booleanos, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $valores
     */
    private function todosFechaHora(array $valores): bool
    {
        $patrones = [
            '/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(:\d{2})?$/',
            '/^\d{2}\/\d{2}\/\d{4}\s+\d{2}:\d{2}(:\d{2})?$/',
            '/^\d{2}-\d{2}-\d{4}\s+\d{2}:\d{2}(:\d{2})?$/',
        ];

        foreach ($valores as $v) {
            $coincide = false;
            foreach ($patrones as $patron) {
                if (preg_match($patron, trim($v)) === 1) {
                    $coincide = true;

                    break;
                }
            }

            if (! $coincide) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $valores
     */
    private function todosFecha(array $valores): bool
    {
        $patrones = [
            '/^\d{4}-\d{2}-\d{2}$/',
            '/^\d{2}\/\d{2}\/\d{4}$/',
            '/^\d{2}-\d{2}-\d{4}$/',
        ];

        foreach ($valores as $v) {
            $coincide = false;
            foreach ($patrones as $patron) {
                if (preg_match($patron, trim($v)) === 1) {
                    $coincide = true;

                    break;
                }
            }

            if (! $coincide) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $valores
     */
    private function todosNumericos(array $valores): bool
    {
        foreach ($valores as $v) {
            $limpio = str_replace(',', '', trim($v));

            if (! is_numeric($limpio)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $valores
     */
    private function todosEnteros(array $valores): bool
    {
        foreach ($valores as $v) {
            $limpio = str_replace(',', '', trim($v));

            if (! is_numeric($limpio)) {
                return false;
            }

            $floatVal = (float) $limpio;
            $intVal = (int) $floatVal;

            if ($floatVal !== (float) $intVal) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $valores
     */
    private function bajaCardinalidad(array $valores): bool
    {
        $unicos = array_unique($valores);

        return count($unicos) >= 2 && count($unicos) <= 8;
    }

    /**
     * @param list<string> $valores
     */
    private function algunoLargo(array $valores): bool
    {
        foreach ($valores as $v) {
            if (mb_strlen($v) > 255) {
                return true;
            }
        }

        return false;
    }
}
