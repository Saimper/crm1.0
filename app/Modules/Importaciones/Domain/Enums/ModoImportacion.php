<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Enums;

/**
 * Modos de procesamiento ante registros existentes.
 *
 * - insert: solo inserta filas nuevas, ignora duplicados.
 * - update: solo actualiza registros existentes, ignora filas que no existen.
 * - upsert: inserta nuevos Y actualiza existentes.
 * - merge: rellena solo columnas nulas/vacías del registro existente.
 * - skip_duplicados: marca la fila como duplicada y continúa (no toca registro).
 * - overwrite: actualiza todas las columnas con valores no-null del CSV (deprecated).
 */
enum ModoImportacion: string
{
    case INSERT = 'insert';
    case UPDATE = 'update';
    case UPSERT = 'upsert';
    case MERGE = 'merge';
    case SKIP_DUPLICADOS = 'skip_duplicados';
    /** @deprecated Usar UPSERT en su lugar */
    case OVERWRITE = 'overwrite';

    public function aplicaA(string $tipoEntidad): bool
    {
        return $tipoEntidad === 'persona' || str_starts_with($tipoEntidad, 'caso_');
    }

    public function actualizaExistente(): bool
    {
        return $this === self::MERGE
            || $this === self::OVERWRITE
            || $this === self::UPDATE
            || $this === self::UPSERT;
    }

    public function pisaCamposLlenos(): bool
    {
        return $this === self::OVERWRITE || $this === self::UPSERT;
    }

    public function label(): string
    {
        return match ($this) {
            self::INSERT => 'Insertar',
            self::UPDATE => 'Actualizar',
            self::UPSERT => 'Insertar y actualizar',
            self::MERGE => 'Completar vacíos',
            self::SKIP_DUPLICADOS => 'Omitir duplicados',
            self::OVERWRITE => 'Sobrescribir',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::INSERT => 'Solo inserta registros nuevos. Las filas duplicadas se ignoran sin error.',
            self::UPDATE => 'Solo actualiza registros existentes. Las filas que no existen se omiten.',
            self::UPSERT => 'Inserta los registros nuevos y actualiza los existentes con los datos del archivo.',
            self::MERGE => 'Rellena únicamente los campos vacíos de registros existentes. No sobrescribe datos.',
            self::SKIP_DUPLICADOS => 'Marca las filas duplicadas como tales y continúa sin modificar nada.',
            self::OVERWRITE => 'Sobrescribe todos los campos del registro existente con los valores del archivo.',
        };
    }

    public function esNuevo(): bool
    {
        return $this === self::INSERT
            || $this === self::UPDATE
            || $this === self::UPSERT;
    }

    public function permiteDuplicados(): bool
    {
        return ! ($this === self::INSERT);
    }
}
