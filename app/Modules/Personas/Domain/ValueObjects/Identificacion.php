<?php

declare(strict_types=1);

namespace App\Modules\Personas\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class Identificacion
{
    public function __construct(public string $valor)
    {
        $limpio = trim($valor);
        $longitud = mb_strlen($limpio);

        if ($limpio === '') {
            throw new InvalidArgumentException('La identificación no puede estar vacía.');
        }
        if ($longitud < 5 || $longitud > 50) {
            throw new InvalidArgumentException("La identificación debe tener entre 5 y 50 caracteres. Recibido: {$longitud}.");
        }
        if (preg_match('/^[A-Za-z0-9.\- ]+$/', $limpio) !== 1) {
            throw new InvalidArgumentException("La identificación contiene caracteres no permitidos: {$valor}");
        }
    }

    public function asString(): string
    {
        return $this->valor;
    }
}
