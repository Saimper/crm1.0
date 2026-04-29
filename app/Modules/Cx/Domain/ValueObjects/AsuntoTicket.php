<?php

declare(strict_types=1);

namespace App\Modules\Cx\Domain\ValueObjects;

use App\Modules\Cx\Domain\Exceptions\DatosTicketInvalidos;

final readonly class AsuntoTicket
{
    public function __construct(public string $valor)
    {
        $normalizado = trim($valor);
        if ($normalizado === '') {
            throw new DatosTicketInvalidos('El asunto del ticket no puede estar vacío.');
        }
        if (mb_strlen($normalizado) > 255) {
            throw new DatosTicketInvalidos('El asunto no puede exceder 255 caracteres.');
        }
    }
}
