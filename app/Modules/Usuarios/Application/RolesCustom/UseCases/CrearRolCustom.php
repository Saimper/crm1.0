<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Application\RolesCustom\UseCases;

use App\Modules\Usuarios\Application\RolesCustom\DTOs\EntradaRolCustom;
use App\Modules\Usuarios\Domain\RolesCustom\Contracts\RepositorioRolCustom;
use App\Modules\Usuarios\Domain\RolesCustom\Entities\RolCustom;
use App\Modules\Usuarios\Domain\RolesCustom\Exceptions\CodigoRolCustomDuplicado;
use App\Modules\Usuarios\Domain\RolesCustom\ValueObjects\CodigoRolCustom;

final class CrearRolCustom
{
    public function __construct(
        private readonly RepositorioRolCustom $repositorio,
    ) {}

    public function execute(EntradaRolCustom $entrada, int $usuarioCreadorId): int
    {
        $codigo = new CodigoRolCustom($entrada->codigo);

        if ($this->repositorio->existeCodigoEnProyecto($codigo->asString(), $entrada->proyectoId)) {
            throw CodigoRolCustomDuplicado::enProyecto($codigo->asString(), $entrada->proyectoId);
        }

        $rol = RolCustom::nuevo(
            proyectoId: $entrada->proyectoId,
            codigo: $codigo,
            nombre: $entrada->nombre,
            descripcion: $entrada->descripcion,
            permisos: array_values($entrada->permisos),
        );

        return $this->repositorio->guardar($rol, $usuarioCreadorId);
    }
}
