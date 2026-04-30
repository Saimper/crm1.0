<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Application\RolesCustom\UseCases;

use App\Modules\Usuarios\Domain\RolesCustom\Contracts\RepositorioRolCustom;
use App\Modules\Usuarios\Domain\RolesCustom\Exceptions\RolCustomNoEditable;
use DomainException;

final class EliminarRolCustom
{
    public function __construct(
        private readonly RepositorioRolCustom $repositorio,
    ) {}

    public function execute(int $rolCustomId): void
    {
        $rol = $this->repositorio->buscarPorId($rolCustomId);
        if ($rol === null) {
            throw new DomainException("Rol custom {$rolCustomId} no existe.");
        }

        if ($this->repositorio->tieneAsignacionesActivas($rolCustomId)) {
            throw RolCustomNoEditable::tieneAsignacionesActivas($rol->codigo->asString());
        }

        $this->repositorio->eliminarLogico($rolCustomId);
    }
}
