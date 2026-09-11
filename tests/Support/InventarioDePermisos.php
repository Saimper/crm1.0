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
 * La comparación no es por igualdad —el middleware los escribe pegados, como
 * `can:casos.exportar`— pero tampoco por subcadena a secas: el código tiene que
 * aparecer con FRONTERA, es decir sin una letra, un dígito, un punto, un guion
 * o un guion bajo pegados a los lados. Sin esa frontera, `casos.ver` se daría
 * por cableado porque existe `casos.ver_todos`, y un permiso nuevo entraría en
 * verde a costa de parecerse a uno viejo.
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
                    if (self::apareceConFrontera($literal, $codigo)) {
                        return false;
                    }
                }

                return true;
            },
        ));

        sort($huerfanos);

        return $huerfanos;
    }

    /**
     * Si `$codigo` aparece en `$literal` sin caracteres de código pegados.
     *
     * `can:casos.exportar` cuenta; `casos.exportar_masivo` no, y `mis.casos.ver`
     * tampoco, que es lo que evita que un permiso se apoye en el nombre de otro.
     */
    private static function apareceConFrontera(string $literal, string $codigo): bool
    {
        $desde = 0;

        while (($pos = strpos($literal, $codigo, $desde)) !== false) {
            $antes = $pos === 0 ? '' : $literal[$pos - 1];
            $despues = $literal[$pos + strlen($codigo)] ?? '';

            if (! self::esCaracterDeCodigo($antes) && ! self::esCaracterDeCodigo($despues)) {
                return true;
            }

            $desde = $pos + 1;
        }

        return false;
    }

    private static function esCaracterDeCodigo(string $caracter): bool
    {
        return $caracter !== '' && (ctype_alnum($caracter) || in_array($caracter, ['.', '_', '-'], true));
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
                // A delegation-protection catalogue does not authorize the
                // underlying operation. Its literals must not make orphaned
                // permissions appear to have acquired a runtime consumer.
                if ($ruta === base_path('app/Modules/Usuarios/Domain/RolesBase/ConfiguracionRolBase.php')) {
                    continue;
                }
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
