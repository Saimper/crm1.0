<?php

declare(strict_types=1);

namespace App\Modules\CamposPersonalizados\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class CodigoCampo
{
    public string $valor;

    public function __construct(string $valor)
    {
        $limpio = strtolower(trim($valor));
        $longitud = mb_strlen($limpio);

        if ($longitud < 2 || $longitud > 80) {
            throw new InvalidArgumentException("Código de campo debe tener entre 2 y 80 caracteres. Recibido: {$longitud}.");
        }
        if (preg_match('/^[a-z][a-z0-9_]*$/', $limpio) !== 1) {
            throw new InvalidArgumentException("Código de campo debe empezar por letra y contener solo letras minúsculas, dígitos y guion bajo. Recibido: {$valor}.");
        }

        $this->valor = $limpio;
    }

    public function asString(): string
    {
        return $this->valor;
    }
}
