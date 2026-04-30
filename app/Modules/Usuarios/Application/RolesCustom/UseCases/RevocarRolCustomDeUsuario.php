<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Application\RolesCustom\UseCases;

use App\Modules\Usuarios\Domain\RolesCustom\Contracts\RepositorioRolCustom;

final class RevocarRolCustomDeUsuario
{
    public function __construct(
        private readonly RepositorioRolCustom $repositorio,
    ) {}

    public function execute(int $rolCustomId, int $usuarioId, int $proyectoId): void
    {
        $this->repositorio->revocarDeUsuario($rolCustomId, $usuarioId, $proyectoId);
    }
}
