<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\Services;

use DateTimeInterface;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Lector XLSX agnóstico al esquema, espejo de LectorCsv. Lee únicamente la primera hoja.
 *
 * - Filas vacías ignoradas.
 * - Cabeceras duplicadas se renombran sufijando "_2", "_3", ... (mismo criterio que LectorCsv).
 * - Celdas DateTime se formatean a 'Y-m-d H:i:s' o 'Y-m-d' si es medianoche, para alinear
 *   con el contrato canónico (procesadores parsean strings vía DateTimeImmutable).
 */
final readonly class LectorXlsx
{
    /** @return list<string> */
    public function leerHeaders(string $path): array
    {
        $primeraFila = $this->primeraFila($path);
        if ($primeraFila === null) {
            return [];
        }

        $cabeceras = array_map(static fn (mixed $c): string => trim((string) self::valorACadena($c)), $primeraFila);

        return $this->desambiguarDuplicados($cabeceras);
    }

    /**
     * @return list<list<string>>
     */
    public function leerFilas(string $path, int $limit = 0): array
    {
        $reader = new Reader;
        $reader->open($path);

        $filas = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $idx = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    if ($idx === 0) {
                        $idx++;

                        continue;
                    }
                    $valores = array_map(static fn (mixed $v): string => self::valorACadena($v), $row->toArray());
                    if (array_filter($valores, static fn (string $s): bool => $s !== '') === []) {
                        continue;
                    }
                    $filas[] = $valores;
                    if ($limit > 0 && count($filas) >= $limit) {
                        return $filas;
                    }
                }
                break;
            }
        } finally {
            $reader->close();
        }

        return $filas;
    }

    public function contarFilas(string $path): int
    {
        $reader = new Reader;
        $reader->open($path);

        $total = 0;
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $idx = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    if ($idx === 0) {
                        $idx++;

                        continue;
                    }
                    $valores = array_map(static fn (mixed $v): string => self::valorACadena($v), $row->toArray());
                    if (array_filter($valores, static fn (string $s): bool => $s !== '') !== []) {
                        $total++;
                    }
                }
                break;
            }
        } finally {
            $reader->close();
        }

        return $total;
    }

    /** @return ?array<int, mixed> */
    private function primeraFila(string $path): ?array
    {
        $reader = new Reader;
        $reader->open($path);
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    return $row->toArray();
                }
            }
        } finally {
            $reader->close();
        }

        return null;
    }

    private static function valorACadena(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if ($v instanceof DateTimeInterface) {
            return $v->format('H:i:s') === '00:00:00' ? $v->format('Y-m-d') : $v->format('Y-m-d H:i:s');
        }
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }

        return (string) $v;
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
