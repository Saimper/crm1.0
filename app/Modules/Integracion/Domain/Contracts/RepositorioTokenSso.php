<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Domain\Contracts;

use App\Modules\Integracion\Domain\Entities\TokenSso;
use DateTimeImmutable;

interface RepositorioTokenSso
{
    public function guardar(TokenSso $token): void;

    public function buscarPorHash(string $hash): ?TokenSso;

    public function marcarConsumido(string $publicId, DateTimeImmutable $consumidoEn): void;
}
