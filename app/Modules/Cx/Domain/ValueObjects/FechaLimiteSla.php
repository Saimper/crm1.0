<?php

declare(strict_types=1);

namespace App\Modules\Cx\Domain\ValueObjects;

use App\Modules\Cx\Domain\Exceptions\DatosResolucionInvalidos;
use DateTimeImmutable;

final readonly class FechaLimiteSla
{
    public function __construct(public DateTimeImmutable $fechaLimite) {}

    public function validarNoPasada(DateTimeImmutable $ahora): void
    {
        if ($this->fechaLimite < $ahora) {
            throw new DatosResolucionInvalidos(
                'La fecha límite SLA no puede ser anterior a ahora: '.$this->fechaLimite->format('Y-m-d H:i').'.'
            );
        }
    }
}
