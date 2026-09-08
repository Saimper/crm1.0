<?php

declare(strict_types=1);

namespace Tests\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Qué códigos de permiso comprueba de verdad el código de la aplicación.
 *
 * Se recorren los literales de cadena, no el texto plano: en PHP con
 * `token_get_all` (así los comentarios, que son otro tipo de token, no cuentan
 * como uso) y en Blade con una pasada de expresión regular sobre el fichero sin
 * sus comentarios, porque una plantilla no es PHP tokenizable y `@can('x')`
 * quedaría fuera de los tokens.
 *
 * La comparación es por «el literal contiene el código», y no por igualdad,
 * porque el middleware los escribe pegados: `can:casos.exportar`. Es holgado a
 * propósito: un falso «se usa» sólo deja pasar un permiso muerto, mientras que
 * un falso «no se usa» rompería el build por nada.
 */
final class InventarioDePermisos
{
    /** @var list<string>|null */
    private static ?array $literales = null;

    /**
     * Los códigos que nadie comprueba, de los que se le pasen.
     *
     * @param  list<string>  $codigos
     * @return list<string>
     */
    public static function sinConsumidor(array $codigos): array
    {
        $literales = self::literales();

        $huerfanos = array_values(array_filter(
            $codigos,
            static function (string $codigo) use ($literales): bool {
                foreach ($literales as $literal) {
                    if (str_contains($literal, $codigo)) {
                        return false;
                    }
                }

                return true;
            },
        ));

        sort($huerfanos);

        return $huerfanos;
    }

    /** @return list<string> */
    private static function literales(): array
    {
        if (self::$literales !== null) {
            return self::$literales;
        }

        $literales = [];

        foreach (['app', 'routes', 'resources/views', 'config'] as $carpeta) {
            foreach (self::ficheros(base_path($carpeta)) as $fichero) {
                $ruta = $fichero->getPathname();
                $contenido = (string) file_get_contents($ruta);

                if (str_ends_with($ruta, '.blade.php')) {
                    $literales = array_merge($literales, self::literalesDeBlade($contenido));

                    continue;
                }

                if (str_ends_with($ruta, '.php')) {
                    $literales = array_merge($literales, self::literalesDePhp($contenido));
                }
            }
        }

        return self::$literales = $literales;
    }

    /** @return list<SplFileInfo> */
    private static function ficheros(string $raiz): array
    {
        if (! is_dir($raiz)) {
            return [];
        }

        $ficheros = [];
        $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, RecursiveDirectoryIterator::SKIP_DOTS));

        /** @var SplFileInfo $fichero */
        foreach ($iterador as $fichero) {
            if ($fichero->isFile()) {
                $ficheros[] = $fichero;
            }
        }

        return $ficheros;
    }

    /** @return list<string> */
    private static function literalesDePhp(string $contenido): array
    {
        $literales = [];

        foreach (token_get_all($contenido) as $token) {
            if (is_array($token) && in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
                $literales[] = trim($token[1], "'\"");
            }
        }

        return $literales;
    }

    /** @return list<string> */
    private static function literalesDeBlade(string $contenido): array
    {
        $sinComentarios = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $contenido);

        preg_match_all('/[\'"]([^\'"\n]{3,120})[\'"]/', $sinComentarios, $coincidencias);

        return $coincidencias[1];
    }
}
