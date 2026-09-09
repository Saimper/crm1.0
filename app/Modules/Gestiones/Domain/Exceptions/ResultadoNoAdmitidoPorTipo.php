<?php

declare(strict_types=1);

namespace App\Modules\Gestiones\Domain\Exceptions;

use DomainException;

/**
 * El resultado elegido no está entre los que ese tipo de gestión admite.
 *
 * La compatibilidad se declara en `resultado_tipo_gestion` y se comprueba aquí,
 * no sólo al pintar el `<select>`: el `resultadoId` viaja en una propiedad
 * pública de Livewire y un payload manipulado lo elige (§11, segunda capa).
 */
final class ResultadoNoAdmitidoPorTipo extends DomainException
{
    public static function para(int $tipoGestionId, int $resultadoId): self
    {
        return new self(
            "El resultado {$resultadoId} no está permitido para el tipo de gestión {$tipoGestionId}."
        );
    }
}
