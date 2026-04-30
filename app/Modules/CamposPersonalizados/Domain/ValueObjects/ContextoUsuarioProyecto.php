<?php

declare(strict_types=1);

namespace App\Modules\CamposPersonalizados\Domain\ValueObjects;

/**
 * Contexto inmutable necesario para resolver tokens de auto-relleno (§7.4 CLAUDE.md).
 * Pasado explícitamente desde la capa Livewire — el dominio NO conoce auth() ni request().
 */
final readonly class ContextoUsuarioProyecto
{
    public function __construct(
        public int $usuarioId,
        public string $usuarioNombre,
        public string $usuarioEmail,
        public string $proyectoCodigo,
    ) {}
}
