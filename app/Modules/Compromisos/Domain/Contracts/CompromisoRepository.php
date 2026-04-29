<?php

declare(strict_types=1);

namespace App\Modules\Compromisos\Domain\Contracts;

use App\Modules\Compromisos\Domain\Entities\Compromiso;

interface CompromisoRepository
{
    public function save(Compromiso $compromiso): Compromiso;

    public function buscarPorId(int $id): Compromiso;

    public function existenVigentesParaCaso(int $casoId): bool;
}
