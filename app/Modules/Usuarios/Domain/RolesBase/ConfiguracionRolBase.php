<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Domain\RolesBase;

use DomainException;

/** A base role keeps its identity; project decisions override only that role. */
final readonly class ConfiguracionRolBase
{
    public const CODIGOS = ['SUPERVISOR', 'GESTOR', 'AUDITOR'];

    public const PERMISOS_PROTEGIDOS = ['campos.definir', 'entidades.definir', 'roles.gestionar', 'mandante.administrar', 'proyectos.crear'];

    /** @param array<string, bool> $permisos */
    private function __construct(
        public string $codigo,
        public ?int $proyectoId,
        public ?string $nombre,
        public ?string $descripcion,
        public array $permisos,
    ) {}

    /** @param list<string> $permisos */
    public static function plantilla(string $codigo, string $nombre, ?string $descripcion, array $permisos): self
    {
        self::validarCodigo($codigo);
        $nombre = trim($nombre);
        if ($nombre === '' || mb_strlen($nombre) > 100 || mb_strlen($descripcion ?? '') > 500) {
            throw new DomainException('Indica un nombre de hasta 100 caracteres y una descripción de hasta 500.');
        }

        $decisiones = [];
        foreach ($permisos as $permiso) {
            self::validarPermiso($permiso);
            $decisiones[$permiso] = true;
        }
        ksort($decisiones);

        return new self($codigo, null, $nombre, $descripcion === null ? null : trim($descripcion), $decisiones);
    }

    /** @param array<string, string> $estados */
    public static function proyecto(string $codigo, int $proyectoId, array $estados): self
    {
        self::validarCodigo($codigo);
        if ($proyectoId <= 0) {
            throw new DomainException('El proyecto no es válido.');
        }

        $decisiones = [];
        foreach ($estados as $permiso => $estado) {
            self::validarPermiso($permiso);
            if (! in_array($estado, ['heredar', 'permitir', 'denegar'], true)) {
                throw new DomainException('La decisión de permiso no es válida.');
            }
            if ($estado !== 'heredar') {
                $decisiones[$permiso] = $estado === 'permitir';
            }
        }
        ksort($decisiones);

        return new self($codigo, $proyectoId, null, null, $decisiones);
    }

    private static function validarCodigo(string $codigo): void
    {
        if (! in_array($codigo, self::CODIGOS, true)) {
            throw new DomainException('Este rol está protegido y no se puede modificar.');
        }
    }

    private static function validarPermiso(string $codigo): void
    {
        if (in_array($codigo, self::PERMISOS_PROTEGIDOS, true)) {
            throw new DomainException('Este permiso está reservado a la administración del sistema.');
        }
    }
}
