<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\ValueObjects;

use App\Modules\Importaciones\Domain\Enums\EstadoFila;

final readonly class ResultadoFila
{
    public function __construct(
        public EstadoFila $estado,
        public ?string $razon = null,
        public ?int $entidadId = null,
    ) {}

    public static function procesada(?int $entidadId): self
    {
        return new self(EstadoFila::PROCESADA, null, $entidadId);
    }

    public static function duplicada(string $razon, ?int $entidadId = null): self
    {
        return new self(EstadoFila::DUPLICADA, $razon, $entidadId);
    }

    public static function invalida(string $razon): self
    {
        return new self(EstadoFila::INVALIDA, $razon);
    }

    public static function omitida(string $razon): self
    {
        return new self(EstadoFila::OMITIDA, $razon);
    }
}
