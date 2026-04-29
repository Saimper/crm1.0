<?php

declare(strict_types=1);

namespace App\Modules\Venta\Domain\Contracts;

use App\Modules\Venta\Domain\Entities\CasoLeadVenta;

interface CasoLeadVentaRepository
{
    public function save(CasoLeadVenta $lead): CasoLeadVenta;

    public function buscarPorCasoId(int $casoId): ?CasoLeadVenta;

    public function existeCodigoEnProyecto(int $proyectoId, string $codigoLead): bool;
}
