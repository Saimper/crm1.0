<?php

declare(strict_types=1);

namespace App\Modules\Asignaciones\Application\DTOs;

final readonly class AsignacionMasivaResultado
{
    /**
     * @param  array<int, int>  $distribucion  usuarioId => cantidad asignada
     */
    public function __construct(
        public int $asignadas,
        public int $omitidas,
        public array $distribucion,
    ) {}
}
