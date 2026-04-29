<?php

declare(strict_types=1);

namespace App\Modules\Servicio\Domain\Contracts;

use App\Modules\Servicio\Domain\Entities\CompromisoAccionServicio;

interface CompromisoAccionServicioRepository
{
    public function save(CompromisoAccionServicio $accion): CompromisoAccionServicio;

    public function buscarPorCompromisoId(int $compromisoId): ?CompromisoAccionServicio;
}
