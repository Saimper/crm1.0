<?php

declare(strict_types=1);

namespace Tests\Feature\UI;

use PHPUnit\Framework\TestCase;

/**
 * Trinquete de estilos en línea.
 *
 * Al abrir la ola 05 había 1.261 atributos `style` repartidos por 98 vistas.
 * Casi todos usaban los tokens —no eran colores inventados— pero repetían la
 * misma decisión de diseño en noventa y nueve ficheros, así que cambiar el alto
 * de una barra de filtros costaba once. El sistema de diseño existía y casi no
 * se usaba.
 *
 * Este test no exige migrarlas todas: exige que el número de cada vista sólo
 * pueda BAJAR. Es el mismo trato que `bin/verificar-fugas.sh` da a las fugas de
 * aislamiento, y por la misma razón: una deuda grande se cierra con un
 * trinquete, no con una promesa.
 *
 * Para bajar el listón después de migrar una vista:
 *
 *     bin/estilos-en-linea.sh --actualizar
 */
final class EstilosEnLineaTest extends TestCase
{
    private const REFERENCIA = 'tests/estilos-en-linea.baseline';

    public function test_ninguna_vista_gana_estilos_en_linea(): void
    {
        $referencia = $this->referencia();
        $actual = $this->inventario();

        $subidas = [];

        foreach ($actual as $vista => $cuantos) {
            $tope = $referencia[$vista] ?? 0;
            if ($cuantos > $tope) {
                $subidas[] = sprintf('%s: %d → %d', $vista, $tope, $cuantos);
            }
        }

        self::assertSame([], $subidas, sprintf(
            "Estas vistas ganaron estilos en línea:\n  - %s\n"
            .'Lo que se escribe en un `style` no lo puede cambiar nadie desde el sistema de diseño. '
            .'Usa las clases de `resources/css/app.css` o un componente de `resources/views/components/ui`.',
            implode("\n  - ", $subidas),
        ));
    }

    /**
     * Y la lista dice la verdad: una vista que ya se migró no puede quedarse
     * con su número viejo, porque entonces el trinquete deja hueco para volver
     * a llenarla sin que nadie se entere.
     */
    public function test_la_lista_de_referencia_esta_al_dia(): void
    {
        $referencia = $this->referencia();
        $actual = $this->inventario();

        $holgadas = [];

        foreach ($referencia as $vista => $tope) {
            $cuantos = $actual[$vista] ?? 0;
            if ($cuantos < $tope) {
                $holgadas[] = sprintf('%s: %d → %d', $vista, $tope, $cuantos);
            }
        }

        self::assertSame([], $holgadas, sprintf(
            "Estas vistas tienen menos estilos de los que dice la lista:\n  - %s\nCierra el hueco con:\n\n    bin/estilos-en-linea.sh --actualizar\n",
            implode("\n  - ", $holgadas),
        ));
    }

    /** @return array<string, int> ruta => cuántos */
    private function inventario(): array
    {
        $raiz = dirname(__DIR__, 3);
        $salida = [];
        $vistas = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($raiz.'/resources/views', \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $fichero */
        foreach ($vistas as $fichero) {
            if (! $fichero->isFile() || ! str_ends_with($fichero->getFilename(), '.blade.php')) {
                continue;
            }

            $cuantos = substr_count((string) file_get_contents($fichero->getPathname()), 'style="');
            if ($cuantos > 0) {
                $salida[str_replace($raiz.'/', '', $fichero->getPathname())] = $cuantos;
            }
        }

        ksort($salida);

        return $salida;
    }

    /** @return array<string, int> */
    private function referencia(): array
    {
        $ruta = dirname(__DIR__, 3).'/'.self::REFERENCIA;
        $lineas = file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertNotFalse($lineas, 'Falta '.self::REFERENCIA);

        $referencia = [];
        foreach ($lineas as $linea) {
            [$cuantos, $vista] = preg_split('/\s+/', trim($linea), 2) + [1 => ''];
            if ($vista !== '') {
                $referencia[$vista] = (int) $cuantos;
            }
        }

        return $referencia;
    }
}
