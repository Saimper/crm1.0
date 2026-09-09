<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Exceptions;

use DomainException;

/**
 * Una fila concreta no puede convertirse en persona o caso. A diferencia de
 * `ImportacionNoProcesable`, la importación sigue: la fila queda inválida con
 * este texto y el lote continúa.
 *
 * Texto fijo, sin datos de la fila: por eso puede ir a `mensaje_error`.
 */
final class FilaNoImportable extends DomainException implements MensajeAptoParaPantalla
{
    public static function sinIdentificacion(): self
    {
        return new self('No se puede crear la persona sin identificación.');
    }
}
