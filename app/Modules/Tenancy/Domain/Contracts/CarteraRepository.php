<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Domain\Contracts;

use App\Modules\Tenancy\Domain\Entities\Cartera;
use App\Modules\Tenancy\Domain\ValueObjects\CodigoCartera;

interface CarteraRepository
{
    public function save(Cartera $cartera): Cartera;

    public function buscarPorId(int $id): Cartera;

    public function existePorCodigoEnProyecto(int $proyectoId, CodigoCartera $codigo): bool;
}
