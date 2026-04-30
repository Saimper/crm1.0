<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\ValueObjects;

final readonly class ResumenChunk
{
    public function __construct(
        public int $procesadas,
        public int $validas,
        public int $invalidas,
        public int $omitidas,
        public int $duplicadas,
        public int $filasEnChunk,
        public ?int $ultimaFilaId,
    ) {}

    public static function vacio(): self
    {
        return new self(0, 0, 0, 0, 0, 0, null);
    }
}
