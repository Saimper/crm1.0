<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Application\RolesCustom\DTOs;

/**
 * DTO de entrada para crear/actualizar rol custom.
 *
 * @phpstan-type Permisos list<string>
 */
final readonly class EntradaRolCustom
{
    /**
     * @param  list<string>  $permisos  códigos de permisos (ej. ['casos.ver', 'gestiones.crear']).
     */
    public function __construct(
        public int $proyectoId,
        public string $codigo,
        public string $nombre,
        public ?string $descripcion,
        public array $permisos,
    ) {}
}
