<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Domain\RolesCustom\Contracts;

use App\Modules\Usuarios\Domain\RolesCustom\Entities\RolCustom;

interface RepositorioRolCustom
{
    public function existeCodigoEnProyecto(string $codigo, int $proyectoId, ?int $excluirId = null): bool;

    public function buscarPorId(int $id): ?RolCustom;

    public function buscarPorCodigo(string $codigo, int $proyectoId): ?RolCustom;

    public function guardar(RolCustom $rol, int $usuarioCreadorId): int;

    public function actualizar(RolCustom $rol): void;

    public function eliminarLogico(int $id): void;

    public function tieneAsignacionesActivas(int $id): bool;

    public function asignarAUsuario(int $rolCustomId, int $usuarioId, int $proyectoId): void;

    public function revocarDeUsuario(int $rolCustomId, int $usuarioId, int $proyectoId): void;
}
