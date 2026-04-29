<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Domain\Contracts;

use App\Modules\Tenancy\Domain\Entities\Proyecto;
use App\Modules\Tenancy\Domain\ValueObjects\CodigoProyecto;

interface ProyectoRepository
{
    public function save(Proyecto $proyecto): Proyecto;

    public function buscarPorId(int $id): Proyecto;

    public function existePorCodigoEnMandante(int $mandanteId, CodigoProyecto $codigo): bool;
}
