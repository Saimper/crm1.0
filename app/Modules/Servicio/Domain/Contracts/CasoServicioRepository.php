<?php

declare(strict_types=1);

namespace App\Modules\Servicio\Domain\Contracts;

use App\Modules\Servicio\Domain\Entities\CasoServicio;

interface CasoServicioRepository
{
    public function save(CasoServicio $servicio): CasoServicio;

    public function buscarPorCasoId(int $casoId): ?CasoServicio;

    public function existeCodigoEnProyecto(int $proyectoId, string $codigoServicio): bool;
}
