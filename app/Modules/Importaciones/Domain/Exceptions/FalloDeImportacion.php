<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Exceptions;

use App\Modules\Importaciones\Domain\ValueObjects\FalloDescrito;
use RuntimeException;

/**
 * Lo que sale del motor cuando una importación ya quedó marcada como fallida.
 *
 * Lleva el motivo apto para pantalla y la referencia del log, y NO lleva la
 * excepción original como `previous` a propósito: el worker de la cola
 * registra lo que se le lanza, cadena de causas incluida, y así el SQL con
 * los datos del cliente acababa otra vez en `failed_jobs` y en el log del
 * worker después de haberlo mantenido fuera de `error_global`. El detalle ya
 * está en el log, bajo la referencia, escrito una sola vez por el descriptor.
 */
final class FalloDeImportacion extends RuntimeException
{
    public function __construct(
        string $motivo,
        public readonly string $referencia,
    ) {
        parent::__construct($motivo);
    }

    public static function desde(FalloDescrito $fallo): self
    {
        return new self($fallo->motivo, $fallo->referencia);
    }

    public function descrito(): FalloDescrito
    {
        return new FalloDescrito($this->getMessage(), $this->referencia);
    }
}
