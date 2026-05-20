<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\ValueObjects;

use App\Modules\Importaciones\Domain\ValueObjects\ResultadoFila;

/**
 * Resultado de la validación en seco (dry-run) de una importación.
 */
final readonly class ResultadoDryRun
{
    /**
     * @param list<ResultadoFila> $erroresMuestra
     * @param list<string> $camposPersonalizadosACrear
     * @param list<string> $advertencias
     */
    public function __construct(
        public bool $esValido,
        public int $filasTotales,
        public int $filasValidas,
        public int $filasConAdvertencia,
        public int $filasInvalidas,
        public array $erroresMuestra,
        public array $camposPersonalizadosACrear,
        public array $advertencias,
    ) {}

    public function puedeEjecutarse(): bool
    {
        return $this->esValido && $this->filasValidas > 0;
    }

    public function resumenTexto(): string
    {
        return sprintf(
            '%d filas: %d válidas, %d con advertencia, %d inválidas',
            $this->filasTotales,
            $this->filasValidas,
            $this->filasConAdvertencia,
            $this->filasInvalidas,
        );
    }
}
