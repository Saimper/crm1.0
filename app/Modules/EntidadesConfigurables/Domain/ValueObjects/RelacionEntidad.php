<?php

declare(strict_types=1);

namespace App\Modules\EntidadesConfigurables\Domain\ValueObjects;

/**
 * Relación opcional de una entidad configurable con el núcleo.
 * Limitado a núcleo para mantener la línea roja §20: no se permiten relaciones
 * dinámicas entre dos entidades configurables.
 */
enum RelacionEntidad: string
{
    case NINGUNA = 'ninguna';
    case CASO    = 'caso';
    case PERSONA = 'persona';
}
