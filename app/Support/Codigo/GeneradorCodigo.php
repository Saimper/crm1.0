<?php

declare(strict_types=1);

namespace App\Support\Codigo;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * Helper puro para derivar / normalizar / resolver conflictos en el campo
 * `codigo` de catálogos, mandantes, proyectos, equipos, etc.
 *
 * Política UI (B6):
 *   - Código vacío → derivar del nombre.
 *   - Código escrito por el usuario → normalizar tolerante (acepta mixed-case
 *     y guion, los convierte a UPPER_SNAKE / lower_snake).
 *   - Conflicto unique → sufijar `_2`, `_3`, ... hasta `_99`.
 *
 * Sin estado. Sin booteo Laravel completo (solo `Str::ascii`).
 */
final class GeneradorCodigo
{
    private const FALLBACK_PREFIX = 'COD';

    /**
     * Deriva un código a partir de un nombre legible.
     */
    public static function derivar(string $nombre, int $maxLen = 50, bool $minusculas = false): string
    {
        $base = self::limpiar($nombre);

        if (strlen($base) < 2) {
            $base = self::FALLBACK_PREFIX.'_'.Str::random(6);
        }

        $base = self::truncar($base, $maxLen);

        return $minusculas ? strtolower($base) : strtoupper($base);
    }

    /**
     * Normaliza una entrada del usuario (tolerante: acepta guion, mixed case,
     * espacios) al formato canónico esperado por la BD.
     */
    public static function normalizar(string $codigo, int $maxLen = 50, bool $minusculas = false): string
    {
        $base = self::limpiar($codigo);

        if (strlen($base) < 2) {
            $base = self::FALLBACK_PREFIX.'_'.Str::random(6);
        }

        $base = self::truncar($base, $maxLen);

        return $minusculas ? strtolower($base) : strtoupper($base);
    }

    /**
     * Reglas Laravel para el input del form. Acepta vacío (auto-derivado luego)
     * o cadena tolerante (mixed case, guion, underscore). El backend normaliza
     * antes de persistir, así que aquí solo se descarta basura agresiva.
     *
     * @return array<int, string>
     */
    public static function reglaValidacion(int $maxLen = 50): array
    {
        return [
            'nullable',
            'string',
            'max:'.$maxLen,
            'regex:/^[A-Za-z0-9_\-\s]*$/',
        ];
    }

    /**
     * Resuelve conflicto unique sufijando `_2`, `_3`, ..., hasta `_99`.
     *
     * @param  callable(string): bool  $existsCheck  retorna true si el código ya existe
     */
    public static function resolverConflicto(string $codigo, callable $existsCheck, int $maxLen = 50): string
    {
        if (! $existsCheck($codigo)) {
            return $codigo;
        }

        for ($i = 2; $i <= 99; $i++) {
            $sufijo = '_'.$i;
            $base = self::truncar($codigo, $maxLen - strlen($sufijo));
            $candidato = $base.$sufijo;
            if (! $existsCheck($candidato)) {
                return $candidato;
            }
        }

        throw new RuntimeException("No se pudo resolver conflicto unique para código '{$codigo}' tras 99 intentos.");
    }

    private static function limpiar(string $entrada): string
    {
        $ascii = Str::ascii(trim($entrada));
        $reemplazado = preg_replace('/[^A-Za-z0-9]+/', '_', $ascii) ?? '';
        $colapsado = preg_replace('/_+/', '_', $reemplazado) ?? '';

        return trim($colapsado, '_');
    }

    private static function truncar(string $valor, int $maxLen): string
    {
        if ($maxLen <= 0) {
            return '';
        }

        return strlen($valor) > $maxLen ? substr($valor, 0, $maxLen) : $valor;
    }
}
