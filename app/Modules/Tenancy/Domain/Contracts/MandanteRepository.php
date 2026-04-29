<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Domain\Contracts;

use App\Modules\Tenancy\Domain\Entities\Mandante;
use App\Modules\Tenancy\Domain\ValueObjects\CodigoMandante;

interface MandanteRepository
{
    public function save(Mandante $mandante): Mandante;

    public function buscarPorId(int $id): Mandante;

    public function existePorCodigo(CodigoMandante $codigo): bool;
}
