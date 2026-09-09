<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Domain\Contracts;

use DateTimeImmutable;

interface RepositorioTokensConsumidos
{
    /**
     * El `jti` lo elige el wrapper, así que el espacio de nombres es de cada
     * cliente: sin el mandante, un mandante puede agotar identificadores de
     * otro y dejarlo sin poder entrar.
     */
    public function fueConsumido(string $jti, int $mandanteId): bool;

    public function registrarConsumo(
        string $jti,
        int $mandanteId,
        ?int $proyectoId,
        DateTimeImmutable $expiraEn,
    ): void;

    public function purgarExpirados(DateTimeImmutable $hasta): int;
}
