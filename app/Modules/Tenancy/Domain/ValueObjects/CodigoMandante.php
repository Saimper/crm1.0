<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class CodigoMandante
{
    public string $valor;

    public function __construct(string $valor)
    {
        $limpio = strtoupper(trim($valor));
        $longitud = mb_strlen($limpio);

        if ($longitud < 2 || $longitud > 50) {
            throw new InvalidArgumentException("Código de mandante debe tener entre 2 y 50 caracteres. Recibido: {$longitud}.");
        }
        if (preg_match('/^[A-Z0-9_-]+$/', $limpio) !== 1) {
            throw new InvalidArgumentException("Código de mandante solo admite letras, dígitos, guion y guion bajo. Recibido: {$valor}.");
        }

        $this->valor = $limpio;
    }

    public function asString(): string
    {
        return $this->valor;
    }
}
