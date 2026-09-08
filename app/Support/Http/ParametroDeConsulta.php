<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\Request;

/**
 * Lectura defensiva de la query string.
 *
 * Un parámetro es lo que quiera quien hace la petición: `?cartera[]=1` llega
 * como array, y castearlo a int o a string da 1 o «Array» con un warning —o un
 * TypeError con strict_types—. Cualquier cosa que no sea un escalar se trata
 * como «no me han pasado filtro», que es el único valor seguro. Nació en el
 * export de auditoría del mandante y aquí lo comparten todas las descargas.
 */
final class ParametroDeConsulta
{
    public static function texto(Request $request, string $clave): string
    {
        $valor = $request->query($clave);

        return is_scalar($valor) ? trim((string) $valor) : '';
    }

    /** Un id: sólo dígitos, o nada. */
    public static function entero(Request $request, string $clave): ?int
    {
        $texto = self::texto($request, $clave);

        return $texto !== '' && ctype_digit($texto) ? (int) $texto : null;
    }

    /** Una fecha `Y-m-d` de calendario, o nada. */
    public static function fecha(Request $request, string $clave): ?string
    {
        $texto = self::texto($request, $clave);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto) !== 1 || ! checkdate((int) substr($texto, 5, 2), (int) substr($texto, 8, 2), (int) substr($texto, 0, 4))) {
            return null;
        }

        return $texto;
    }
}
