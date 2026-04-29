<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Domain\ValueObjects;

final readonly class TokenClaroHash
{
    public string $claro;

    public string $hash;

    private function __construct(string $claro, string $hash)
    {
        $this->claro = $claro;
        $this->hash = $hash;
    }

    public static function generar(string $tokenClaro): self
    {
        return new self($tokenClaro, hash('sha256', $tokenClaro));
    }

    public static function soloHash(string $tokenClaro): self
    {
        return new self($tokenClaro, hash('sha256', $tokenClaro));
    }
}
