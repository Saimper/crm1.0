<?php

declare(strict_types=1);

namespace App\Modules\Cx\Domain\ValueObjects;

use App\Modules\Cx\Domain\Exceptions\DatosTicketInvalidos;

final readonly class CodigoTicket
{
    public function __construct(public string $valor)
    {
        $normalizado = trim($valor);
        if ($normalizado === '') {
            throw new DatosTicketInvalidos('El código de ticket no puede estar vacío.');
        }
        if (mb_strlen($normalizado) > 50) {
            throw new DatosTicketInvalidos('El código de ticket no puede exceder 50 caracteres.');
        }
    }

    public function __toString(): string
    {
        return $this->valor;
    }
}
