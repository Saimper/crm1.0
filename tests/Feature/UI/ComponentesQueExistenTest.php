<?php

declare(strict_types=1);

namespace Tests\Feature\UI;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Que ninguna vista llame a un componente que no existe.
 *
 * Un `<x-ui.chip>` que se quedó atrás no falla al desplegar ni al arrancar:
 * falla la primera vez que alguien abre esa pantalla, con un 500 y el nombre
 * del componente en el log. La ola 05 retiró diez componentes sin uso, y ésta
 * es la red que dice si alguno seguía usándose en un rincón.
 *
 * Mira los componentes propios de `resources/views/components`, no los de
 * Livewire ni los de paquetes. Y mira las VISTAS: un componente invocado desde
 * un `Blade::render()` de un test no entra aquí, lo dice el propio test al
 * fallar.
 */
final class ComponentesQueExistenTest extends TestCase
{
    public function test_toda_referencia_a_un_componente_propio_tiene_su_fichero(): void
    {
        $raiz = dirname(__DIR__, 3);
        $huerfanos = [];

        foreach ($this->vistas($raiz.'/resources/views') as $vista) {
            $contenido = (string) file_get_contents($vista);
            preg_match_all('/<x-([a-z0-9][a-z0-9._-]*)/i', $contenido, $coincidencias);

            foreach (array_unique($coincidencias[1]) as $nombre) {
                if ($this->esDeFuera($nombre) || $this->existe($raiz, $nombre)) {
                    continue;
                }

                $huerfanos[] = sprintf('<x-%s> en %s', $nombre, str_replace($raiz.'/', '', $vista));
            }
        }

        sort($huerfanos);

        self::assertSame([], $huerfanos, "Componentes usados que no existen:\n  - ".implode("\n  - ", $huerfanos));
    }

    private function esDeFuera(string $nombre): bool
    {
        // `slot` es de Blade; el resto son prefijos de paquetes.
        return $nombre === 'slot' || str_starts_with($nombre, 'livewire.');
    }

    private function existe(string $raiz, string $nombre): bool
    {
        $ruta = str_replace('.', '/', $nombre);

        foreach ([
            "/resources/views/components/{$ruta}.blade.php",
            "/resources/views/components/{$ruta}/index.blade.php",
            '/app/View/Components/'.str_replace(' ', '', ucwords(str_replace(['-', '/'], [' ', '\\'], $ruta))).'.php',
        ] as $candidato) {
            if (file_exists($raiz.$candidato)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function vistas(string $carpeta): array
    {
        $vistas = [];
        $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($carpeta, RecursiveDirectoryIterator::SKIP_DOTS));

        /** @var SplFileInfo $fichero */
        foreach ($iterador as $fichero) {
            if ($fichero->isFile() && str_ends_with($fichero->getFilename(), '.blade.php')) {
                $vistas[] = $fichero->getPathname();
            }
        }

        return $vistas;
    }
}
