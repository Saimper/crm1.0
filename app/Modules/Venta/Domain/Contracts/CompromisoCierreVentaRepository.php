<?php

declare(strict_types=1);

namespace App\Modules\Venta\Domain\Contracts;

use App\Modules\Venta\Domain\Entities\CompromisoCierreVenta;

interface CompromisoCierreVentaRepository
{
    public function save(CompromisoCierreVenta $cierre): CompromisoCierreVenta;

    public function buscarPorCompromisoId(int $compromisoId): ?CompromisoCierreVenta;
}
