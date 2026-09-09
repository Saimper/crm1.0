<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Events;

/**
 * Una importación terminó fallida.
 *
 * Lleva el motivo apto para pantalla y la referencia del log, nunca el
 * mensaje crudo de la excepción: un listener que lo notificara o lo guardara
 * volvería a copiar el INSERT con los datos del cliente a otro sitio.
 */
final readonly class ImportacionFallada
{
    public function __construct(
        public int $importacionId,
        public int $proyectoId,
        public string $motivo,
        public string $referencia,
    ) {}
}
