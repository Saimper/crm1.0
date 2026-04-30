<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Enums;

/**
 * Modos de procesamiento ante registros existentes.
 *
 * - merge: rellena solo columnas nulas/vacías del registro existente.
 * - skip_duplicados: marca la fila como duplicada y continúa el batch (no toca registro).
 * - overwrite: actualiza todas las columnas con valores no-null del CSV.
 */
enum ModoImportacion: string
{
    case MERGE = 'merge';
    case SKIP_DUPLICADOS = 'skip_duplicados';
    case OVERWRITE = 'overwrite';

    public function aplicaA(string $tipoEntidad): bool
    {
        return $tipoEntidad === 'persona' || str_starts_with($tipoEntidad, 'caso_');
    }

    public function actualizaExistente(): bool
    {
        return $this === self::MERGE || $this === self::OVERWRITE;
    }

    public function pisaCamposLlenos(): bool
    {
        return $this === self::OVERWRITE;
    }
}
