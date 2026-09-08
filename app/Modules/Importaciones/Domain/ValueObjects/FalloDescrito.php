<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Lo que se le dice al supervisor cuando una importación falla, y la
 * referencia con la que soporte encuentra el detalle en el log.
 *
 * Son dos cosas separadas a propósito: el motivo se guarda en
 * `importaciones.error_global` y se pinta en pantalla, así que no puede llevar
 * el INSERT con los datos de la persona; el detalle completo vive en el log,
 * y la referencia —ocho hexadecimales, que caben en un mensaje de chat— es lo
 * que une las dos mitades.
 */
final readonly class FalloDescrito
{
    public function __construct(
        public string $motivo,
        public string $referencia,
    ) {
        if (trim($motivo) === '') {
            throw new InvalidArgumentException('Un fallo descrito necesita un motivo.');
        }

        if (preg_match('/^[0-9a-f]{8}$/', $referencia) !== 1) {
            throw new InvalidArgumentException('La referencia de un fallo son ocho caracteres hexadecimales.');
        }
    }
}
