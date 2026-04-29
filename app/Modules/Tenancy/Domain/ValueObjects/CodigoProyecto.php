<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class CodigoProyecto
{
    public string $valor;

    public function __construct(string $valor)
    {
        $limpio = strtoupper(trim($valor));
        $longitud = mb_strlen($limpio);

        if ($longitud < 2 || $longitud > 80) {
            throw new InvalidArgumentException("Código de proyecto debe tener entre 2 y 80 caracteres. Recibido: {$longitud}.");
        }
        if (preg_match('/^[A-Z0-9_-]+$/', $limpio) !== 1) {
            throw new InvalidArgumentException("Código de proyecto solo admite letras, dígitos, guion y guion bajo. Recibido: {$valor}.");
        }

        $this->valor = $limpio;
    }

    public function asString(): string
    {
        return $this->valor;
    }
}
