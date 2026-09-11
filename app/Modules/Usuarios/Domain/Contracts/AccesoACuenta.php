<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Domain\Contracts;

interface AccesoACuenta
{
    public function puedeImportar(int $usuarioId, int $proyectoId, ?int $carteraId = null): bool;

    public function puedeGestionar(int $usuarioId, int $proyectoId, int $casoId): bool;

    public function puedeReincorporar(int $usuarioId, int $proyectoId, int $carteraOrigenId, int $carteraDestinoId): bool;
}
