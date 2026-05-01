<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Domain\Contracts;

use DateTimeImmutable;

interface RepositorioTokensConsumidos
{
    public function fueConsumido(string $jti): bool;

    public function registrarConsumo(string $jti, int $proyectoId, DateTimeImmutable $expiraEn): void;

    public function purgarExpirados(DateTimeImmutable $hasta): int;
}
