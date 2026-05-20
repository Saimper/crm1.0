<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Exceptions;

class ImportacionSinPermisoCamposException extends \DomainException
{
    public function __construct(string $accion, int $proyectoId)
    {
        parent::__construct(
            sprintf(
                'No tienes permiso para crear campos personalizados en el proyecto %d. Se requiere el permiso "campos.definir" para %s.',
                $proyectoId,
                $accion,
            ),
        );
    }
}
