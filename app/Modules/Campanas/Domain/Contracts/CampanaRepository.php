<?php

declare(strict_types=1);

namespace App\Modules\Campanas\Domain\Contracts;

use App\Modules\Campanas\Domain\Entities\Campana;
use App\Modules\Campanas\Domain\ValueObjects\CodigoCampana;

interface CampanaRepository
{
    public function save(Campana $campana): Campana;

    public function existePorCodigoEnProyecto(int $proyectoId, CodigoCampana $codigo): bool;
}
