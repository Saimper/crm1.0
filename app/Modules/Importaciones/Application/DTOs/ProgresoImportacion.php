<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\DTOs;

use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;

final readonly class ProgresoImportacion
{
    public function __construct(
        public int $id,
        public string $publicId,
        public EstadoImportacion $estado,
        public ModoImportacion $modo,
        public int $totalFilas,
        public int $procesadas,
        public int $insertadas,
        public int $actualizadas,
        public int $invalidas,
        public int $omitidas,
        public int $duplicadas,
        public ?string $iniciadoEn,
        public ?string $terminadoEn,
        public ?string $errorGlobal,
    ) {}

    public function porcentaje(): int
    {
        if ($this->totalFilas <= 0) {
            return $this->estado === EstadoImportacion::COMPLETADA ? 100 : 0;
        }
        $avance = $this->procesadas + $this->invalidas + $this->omitidas + $this->duplicadas;

        return (int) min(100, floor(($avance / $this->totalFilas) * 100));
    }

    public function enCurso(): bool
    {
        return ! $this->estado->esTerminal();
    }
}
