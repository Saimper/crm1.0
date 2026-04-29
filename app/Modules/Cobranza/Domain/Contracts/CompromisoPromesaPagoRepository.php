<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Domain\Contracts;

use App\Modules\Cobranza\Domain\Entities\CompromisoPromesaPago;

interface CompromisoPromesaPagoRepository
{
    public function save(CompromisoPromesaPago $promesa): CompromisoPromesaPago;

    public function buscarPorCompromisoId(int $compromisoId): ?CompromisoPromesaPago;
}
