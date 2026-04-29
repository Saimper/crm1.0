<?php

declare(strict_types=1);

namespace App\Modules\Cx\Domain\ValueObjects;

use App\Modules\Cx\Domain\Exceptions\DatosResolucionInvalidos;

final readonly class AccionComprometida
{
    public function __construct(public string $valor)
    {
        $normalizado = trim($valor);
        if ($normalizado === '') {
            throw new DatosResolucionInvalidos('La acción comprometida no puede estar vacía.');
        }
        if (mb_strlen($normalizado) > 500) {
            throw new DatosResolucionInvalidos('La acción comprometida no puede exceder 500 caracteres.');
        }
    }
}
