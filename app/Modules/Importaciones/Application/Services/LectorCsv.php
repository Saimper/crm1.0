<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\Services;

/**
 * Lector de CSV agnóstico al esquema. No exige columnas: solo extrae cabeceras
 * y filas como arrays indexados por posición. El consumidor decide qué hacer
 * con cada columna (mapeo libre vía MapeadorPayload).
 *
 * - BOM UTF-8 stripped.
 * - Líneas vacías ignoradas.
 * - Cabeceras duplicadas se renombran sufijando "_2", "_3", ... para conservar índice posicional.
 */
final readonly class LectorCsv
{
    /** @return list<string> */
    public function leerHeaders(string $contenido): array
    {
        $contenido = $this->limpiarBom($contenido);
        $lineas = $this->lineasNoVacias($contenido);
        if ($lineas === []) {
            return [];
        }

        $cabeceras = str_getcsv($lineas[0]);
        $cabeceras = array_map(static fn (?string $c): string => trim((string) $c), $cabeceras);

        return $this->desambiguarDuplicados($cabeceras);
    }

    /**
     * Devuelve filas como arrays indexados por posición (no por header).
     *
     * @return list<list<string>>
     */
    public function leerFilas(string $contenido, int $limit = 0): array
    {
        $contenido = $this->limpiarBom($contenido);
        $lineas = $this->lineasNoVacias($contenido);
        if (count($lineas) < 2) {
            return [];
        }

        $filas = [];
        $totalLineas = count($lineas);
        for ($i = 1; $i < $totalLineas; $i++) {
            $valores = str_getcsv($lineas[$i]);
            $filas[] = array_map(static fn (?string $v): string => (string) $v, $valores);
            if ($limit > 0 && count($filas) >= $limit) {
                break;
            }
        }

        return $filas;
    }

    public function contarFilas(string $contenido): int
    {
        $lineas = $this->lineasNoVacias($this->limpiarBom($contenido));

        return max(0, count($lineas) - 1);
    }

    private function limpiarBom(string $contenido): string
    {
        return (string) preg_replace('/^\xEF\xBB\xBF/', '', $contenido);
    }

    /** @return list<string> */
    private function lineasNoVacias(string $contenido): array
    {
        $lineas = preg_split('/\r\n|\r|\n/', trim($contenido));
        if ($lineas === false) {
            return [];
        }

        return array_values(array_filter($lineas, static fn (string $l): bool => trim($l) !== ''));
    }

    /**
     * @param  list<string>  $cabeceras
     * @return list<string>
     */
    private function desambiguarDuplicados(array $cabeceras): array
    {
        $vistas = [];
        $out = [];
        foreach ($cabeceras as $c) {
            $base = $c === '' ? 'columna' : $c;
            $candidato = $base;
            $sufijo = 2;
            while (isset($vistas[$candidato])) {
                $candidato = $base.'_'.$sufijo;
                $sufijo++;
            }
            $vistas[$candidato] = true;
            $out[] = $candidato;
        }

        return $out;
    }
}
