<?php

declare(strict_types=1);

namespace App\Modules\CamposPersonalizados\Domain\Services;

use App\Modules\CamposPersonalizados\Domain\Exceptions\ReglaViolada;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\AutoFill;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\ContextoUsuarioProyecto;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\MarcadorTemporal;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;

/**
 * Aplica las reglas declarativas de un campo personalizado (§7.4 CLAUDE.md v2).
 * Si algún dato viola una regla lanza ReglaViolada con el motivo.
 *
 * Reglas soportadas (JSON):
 *   obligatorio                  → bool
 *   min / max                    → números/fechas (legacy literal)
 *   longitud_min / longitud_max  → textos
 *   regex                        → string (solo textos)
 *   fecha_minima / fecha_maxima  → tokens MarcadorTemporal (hoy|ahora|±Nd|ISO)
 *   auto_fill                    → token AutoFill (no participa en validación)
 *   solo_lectura_tras_guardar    → bool (no participa en validación)
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
        $this->validarMarcadoresTemporales($timestamp, $reglas, $etiqueta, $valor);
    }

    /** @param array<string, mixed> $reglas */
    private function validarMarcadoresTemporales(int $timestamp, array $reglas, string $etiqueta, string $valor): void
    {
        $tipo = str_contains($valor, 'T') || str_contains($valor, ':')
            ? TipoCampo::FECHA_HORA
            : TipoCampo::FECHA;

        if (isset($reglas['fecha_minima']) && is_string($reglas['fecha_minima']) && $reglas['fecha_minima'] !== '') {
            $minimo = MarcadorTemporal::desde($reglas['fecha_minima']);
            if ($timestamp < $minimo->paraComparar($tipo)) {
                throw ReglaViolada::fechaAnteriorAMinimo($etiqueta, $this->describirMarcador($reglas['fecha_minima']));
            }
        }
        if (isset($reglas['fecha_maxima']) && is_string($reglas['fecha_maxima']) && $reglas['fecha_maxima'] !== '') {
            $maximo = MarcadorTemporal::desde($reglas['fecha_maxima']);
            if ($timestamp > $maximo->paraComparar($tipo)) {
                throw ReglaViolada::fechaPosteriorAMaximo($etiqueta, $this->describirMarcador($reglas['fecha_maxima']));
            }
        }
    }

    private function describirMarcador(string $token): string
    {
        return match ($token) {
            'hoy' => 'hoy',
            'ahora' => 'ahora',
            default => $token,
        };
    }

    /**
     * Resuelve el token `auto_fill` a un valor concreto según el contexto actual.
     * Devuelve `null` cuando no hay regla, el token es desconocido o el tipo no admite el token.
     *
     * @param  array<string, mixed>  $reglas
     */
    public function valorAutoFill(TipoCampo $tipo, array $reglas, ContextoUsuarioProyecto $ctx): ?string
    {
        $token = $reglas['auto_fill'] ?? null;
        if (! is_string($token) || $token === '') {
            return null;
        }

        $auto = AutoFill::tryFrom($token);
        if ($auto === null || ! $auto->tipoCompatible($tipo)) {
            return null;
        }

        return match ($auto) {
            AutoFill::NOW => MarcadorTemporal::desde('ahora')->paraAutoFill($tipo),
            AutoFill::TODAY => MarcadorTemporal::desde('hoy')->paraAutoFill($tipo),
            AutoFill::USUARIO_NOMBRE => $ctx->usuarioNombre,
            AutoFill::USUARIO_EMAIL => $ctx->usuarioEmail,
            AutoFill::PROYECTO_CODIGO => $ctx->proyectoCodigo,
        };
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
