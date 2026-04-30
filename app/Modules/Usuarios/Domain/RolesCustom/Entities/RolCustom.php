<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Domain\RolesCustom\Entities;

use App\Modules\Usuarios\Domain\RolesCustom\Exceptions\PermisoNoAsignableARolCustom;
use App\Modules\Usuarios\Domain\RolesCustom\Exceptions\RolCustomSinPermisos;
use App\Modules\Usuarios\Domain\RolesCustom\ValueObjects\CodigoRolCustom;

/**
 * Entidad de dominio: rol custom dentro de un proyecto.
 *
 * Combinación nombrada de permisos existentes (matriz F22). Permisos `*.definir`
 * están vetados por diseño: son exclusivos de ADMIN_GLOBAL.
 *
 * Inmutable. Cada cambio produce una entidad nueva. La persistencia (UPDATE)
 * se delega al repositorio en el UseCase.
 */
final class RolCustom
{
    /**
     * Lista cerrada de permisos que NUNCA pueden asignarse a un rol custom.
     *
     * Mantener sincronizada con cualquier permiso que el negocio considere
     * exclusivo de ADMIN_GLOBAL.
     */
    public const PERMISOS_VETADOS = [
        'campos.definir',
        'entidades.definir',
        'roles.gestionar',
    ];

    /**
     * @param  list<string>  $permisos  códigos de permisos (ej. 'casos.ver').
     */
    private function __construct(
        public readonly ?int $id,
        public readonly int $proyectoId,
        public readonly CodigoRolCustom $codigo,
        public readonly string $nombre,
        public readonly ?string $descripcion,
        public readonly bool $activo,
        public readonly array $permisos,
    ) {}

    /**
     * @param  list<string>  $permisos
     */
    public static function nuevo(
        int $proyectoId,
        CodigoRolCustom $codigo,
        string $nombre,
        ?string $descripcion,
        array $permisos,
    ): self {
        $instancia = new self(
            id: null,
            proyectoId: $proyectoId,
            codigo: $codigo,
            nombre: trim($nombre),
            descripcion: $descripcion === null ? null : trim($descripcion),
            activo: true,
            permisos: array_values(array_unique($permisos)),
        );
        $instancia->validar();

        return $instancia;
    }

    /**
     * @param  list<string>  $permisos
     */
    public static function reconstituir(
        int $id,
        int $proyectoId,
        CodigoRolCustom $codigo,
        string $nombre,
        ?string $descripcion,
        bool $activo,
        array $permisos,
    ): self {
        return new self($id, $proyectoId, $codigo, $nombre, $descripcion, $activo, $permisos);
    }

    /**
     * @param  list<string>  $permisos
     */
    public function actualizar(string $nombre, ?string $descripcion, array $permisos): self
    {
        $instancia = new self(
            id: $this->id,
            proyectoId: $this->proyectoId,
            codigo: $this->codigo,
            nombre: trim($nombre),
            descripcion: $descripcion === null ? null : trim($descripcion),
            activo: $this->activo,
            permisos: array_values(array_unique($permisos)),
        );
        $instancia->validar();

        return $instancia;
    }

    public function desactivar(): self
    {
        return new self(
            id: $this->id,
            proyectoId: $this->proyectoId,
            codigo: $this->codigo,
            nombre: $this->nombre,
            descripcion: $this->descripcion,
            activo: false,
            permisos: $this->permisos,
        );
    }

    public static function puedeAsignarPermiso(string $codigoPermiso): bool
    {
        return ! in_array($codigoPermiso, self::PERMISOS_VETADOS, true);
    }

    private function validar(): void
    {
        if (trim($this->nombre) === '') {
            throw new \DomainException('El nombre del rol custom no puede estar vacío.');
        }
        if ($this->permisos === []) {
            throw RolCustomSinPermisos::nuevo();
        }
        foreach ($this->permisos as $codigo) {
            if (! self::puedeAsignarPermiso($codigo)) {
                throw PermisoNoAsignableARolCustom::porCodigo($codigo);
            }
        }
    }
}
