<?php

declare(strict_types=1);

namespace App\Modules\CamposPersonalizados\Domain\Services;

use App\Modules\CamposPersonalizados\Domain\Exceptions\ReglaViolada;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;

/**
 * Aplica las reglas declarativas de un campo personalizado (§7.4 CLAUDE.md v2).
 * Si algún dato viola una regla lanza ReglaViolada con el motivo.
 *
 * Reglas soportadas (JSON):
 *   obligatorio       → bool
 *   min / max         → números/fechas
 *   longitud_min      → textos
 *   longitud_max      → textos
 *   regex             → string (solo textos)
 */
final class EvaluadorReglas
{
    /**
     * @param  array<string, mixed>  $reglas
     */
    public function validar(TipoCampo $tipo, mixed $valor, array $reglas, bool $obligatorio, string $etiqueta): void
    {
        $esVacio = $this->esVacio($tipo, $valor);

        if ($obligatorio && $esVacio) {
            throw new ReglaViolada("El campo «{$etiqueta}» es obligatorio.");
        }
        if ($esVacio) {
            return; // No obligatorio y vacío → OK.
        }

        match ($tipo) {
            TipoCampo::TEXTO_CORTO, TipoCampo::TEXTO_LARGO => $this->validarTexto((string) $valor, $reglas, $etiqueta),
            TipoCampo::NUMERO_ENTERO => $this->validarNumeroEntero($valor, $reglas, $etiqueta),
            TipoCampo::NUMERO_DECIMAL, TipoCampo::MONEDA => $this->validarNumeroDecimal($valor, $reglas, $etiqueta),
            TipoCampo::FECHA, TipoCampo::FECHA_HORA => $this->validarFecha((string) $valor, $reglas, $etiqueta),
            TipoCampo::BOOLEANO => $this->validarBooleano($valor, $etiqueta),
            TipoCampo::SELECCION_UNICA => null, // La existencia del opcion_id se valida en Application.
            TipoCampo::SELECCION_MULTIPLE => $this->validarSeleccionMultiple($valor, $etiqueta),
        };
    }

    private function esVacio(TipoCampo $tipo, mixed $valor): bool
    {
        if ($valor === null) {
            return true;
        }
        if ($tipo === TipoCampo::BOOLEANO) {
            return false;
        }
        if (is_string($valor) && trim($valor) === '') {
            return true;
        }
        if ($tipo === TipoCampo::SELECCION_MULTIPLE && is_array($valor) && $valor === []) {
            return true;
        }

        return false;
    }

    /** @param array<string, mixed> $reglas */
    private function validarTexto(string $valor, array $reglas, string $etiqueta): void
    {
        $len = mb_strlen($valor);
        if (isset($reglas['longitud_min']) && $len < (int) $reglas['longitud_min']) {
            throw new ReglaViolada("El campo «{$etiqueta}» debe tener al menos {$reglas['longitud_min']} caracteres.");
        }
        if (isset($reglas['longitud_max']) && $len > (int) $reglas['longitud_max']) {
            throw new ReglaViolada("El campo «{$etiqueta}» excede los {$reglas['longitud_max']} caracteres permitidos.");
        }
        if (isset($reglas['regex']) && preg_match('/'.$reglas['regex'].'/u', $valor) !== 1) {
            throw new ReglaViolada("El campo «{$etiqueta}» no cumple con el formato requerido.");
        }
    }

    /** @param array<string, mixed> $reglas */
    private function validarNumeroEntero(mixed $valor, array $reglas, string $etiqueta): void
    {
        if (! is_int($valor) && ! (is_string($valor) && preg_match('/^-?\d+$/', $valor) === 1)) {
            throw new ReglaViolada("El campo «{$etiqueta}» debe ser un número entero.");
        }
        $v = (int) $valor;
        if (isset($reglas['min']) && $v < (int) $reglas['min']) {
            throw new ReglaViolada("El campo «{$etiqueta}» debe ser mayor o igual a {$reglas['min']}.");
        }
        if (isset($reglas['max']) && $v > (int) $reglas['max']) {
            throw new ReglaViolada("El campo «{$etiqueta}» debe ser menor o igual a {$reglas['max']}.");
        }
    }

    /** @param array<string, mixed> $reglas */
    private function validarNumeroDecimal(mixed $valor, array $reglas, string $etiqueta): void
    {
        if (! is_numeric($valor)) {
            throw new ReglaViolada("El campo «{$etiqueta}» debe ser numérico.");
        }
        $v = (float) $valor;
        if (isset($reglas['min']) && $v < (float) $reglas['min']) {
            throw new ReglaViolada("El campo «{$etiqueta}» debe ser mayor o igual a {$reglas['min']}.");
        }
        if (isset($reglas['max']) && $v > (float) $reglas['max']) {
            throw new ReglaViolada("El campo «{$etiqueta}» debe ser menor o igual a {$reglas['max']}.");
        }
    }

    /** @param array<string, mixed> $reglas */
    private function validarFecha(string $valor, array $reglas, string $etiqueta): void
    {
        $timestamp = strtotime($valor);
        if ($timestamp === false) {
            throw new ReglaViolada("El campo «{$etiqueta}» no es una fecha válida.");
        }
        if (isset($reglas['min'])) {
            $minTs = strtotime((string) $reglas['min']);
            if ($minTs !== false && $timestamp < $minTs) {
                throw new ReglaViolada("El campo «{$etiqueta}» no puede ser anterior a {$reglas['min']}.");
            }
        }
        if (isset($reglas['max'])) {
            $maxTs = strtotime((string) $reglas['max']);
            if ($maxTs !== false && $timestamp > $maxTs) {
                throw new ReglaViolada("El campo «{$etiqueta}» no puede ser posterior a {$reglas['max']}.");
            }
        }
    }

    private function validarBooleano(mixed $valor, string $etiqueta): void
    {
        if (! is_bool($valor) && ! in_array($valor, [0, 1, '0', '1', 'true', 'false'], true)) {
            throw new ReglaViolada("El campo «{$etiqueta}» debe ser verdadero o falso.");
        }
    }

    private function validarSeleccionMultiple(mixed $valor, string $etiqueta): void
    {
        if (! is_array($valor)) {
            throw new ReglaViolada("El campo «{$etiqueta}» debe ser una lista de opciones.");
        }
        foreach ($valor as $id) {
            if (! is_int($id) && ! (is_string($id) && preg_match('/^\d+$/', $id) === 1)) {
                throw new ReglaViolada("El campo «{$etiqueta}» contiene opciones inválidas.");
            }
        }
    }
}
