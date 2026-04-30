<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Application\RolesCustom\UseCases;

use App\Modules\Usuarios\Domain\RolesCustom\Contracts\RepositorioRolCustom;
use DomainException;

final class AsignarRolCustomAUsuario
{
    public function __construct(
        private readonly RepositorioRolCustom $repositorio,
    ) {}

    public function execute(int $rolCustomId, int $usuarioId, int $proyectoId): void
    {
        $rol = $this->repositorio->buscarPorId($rolCustomId);
        if ($rol === null) {
            throw new DomainException("Rol custom {$rolCustomId} no existe.");
        }

        if ($rol->proyectoId !== $proyectoId) {
            throw new DomainException(
                "Rol custom pertenece al proyecto {$rol->proyectoId}, no al {$proyectoId}.",
            );
        }

        if (! $rol->activo) {
            throw new DomainException("Rol custom '{$rol->codigo->asString()}' está inactivo.");
        }

        $this->repositorio->asignarAUsuario($rolCustomId, $usuarioId, $proyectoId);
    }
}
