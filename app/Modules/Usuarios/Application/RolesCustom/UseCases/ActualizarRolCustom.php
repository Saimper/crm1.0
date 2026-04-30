<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Application\RolesCustom\UseCases;

use App\Modules\Usuarios\Application\RolesCustom\DTOs\EntradaRolCustom;
use App\Modules\Usuarios\Domain\RolesCustom\Contracts\RepositorioRolCustom;
use DomainException;

final class ActualizarRolCustom
{
    public function __construct(
        private readonly RepositorioRolCustom $repositorio,
    ) {}

    public function execute(int $rolCustomId, EntradaRolCustom $entrada): void
    {
        $rol = $this->repositorio->buscarPorId($rolCustomId);
        if ($rol === null) {
            throw new DomainException("Rol custom {$rolCustomId} no existe.");
        }

        if ($rol->proyectoId !== $entrada->proyectoId) {
            throw new DomainException('El proyecto del rol no coincide con el de la entrada.');
        }

        $actualizado = $rol->actualizar(
            nombre: $entrada->nombre,
            descripcion: $entrada->descripcion,
            permisos: array_values($entrada->permisos),
        );

        $this->repositorio->actualizar($actualizado);
    }
}
