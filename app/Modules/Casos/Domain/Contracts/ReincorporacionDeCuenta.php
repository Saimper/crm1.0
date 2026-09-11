<?php

declare(strict_types=1);

namespace App\Modules\Casos\Domain\Contracts;

interface ReincorporacionDeCuenta
{
    public function execute(int $proyectoId, int $casoId, int $carteraDestinoId, int $importacionId, int $usuarioId): void;
}
