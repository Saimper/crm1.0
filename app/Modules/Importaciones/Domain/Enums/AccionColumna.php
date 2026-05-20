<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Enums;

/**
 * Acción que se tomará con una columna del archivo importado.
 */
enum AccionColumna: string
{
    case MAPEAR_SISTEMA = 'mapear_sistema';
    case CREAR_CP = 'crear_cp';
    case IGNORAR = 'ignorar';
}
