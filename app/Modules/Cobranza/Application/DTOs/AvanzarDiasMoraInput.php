<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Application\DTOs;

final readonly class AvanzarDiasMoraInput
{
    /**
     * @param  int|null  $proyectoId  Nulo para recorrer todos los proyectos de cobranza.
     * @param  bool  $simulacro  Cuenta lo que avanzaría sin escribir nada.
     */
    public function __construct(
        public ?int $proyectoId = null,
        public bool $simulacro = false,
    ) {}
}
