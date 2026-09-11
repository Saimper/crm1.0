<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Domain\Contracts;

interface AccesoAReparto
{
    public function puedeRepartir(int $usuarioId, int $proyectoId, ?int $carteraId = null): bool;

    public function puedeRecibir(int $usuarioId, int $proyectoId, int $carteraId): bool;
}
