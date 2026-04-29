<?php

declare(strict_types=1);

namespace App\Modules\Promesas\Domain\Contracts;

use App\Modules\Promesas\Domain\Entities\Promesa;

interface PromesaRepository
{
    public function save(Promesa $promesa): Promesa;

    public function buscarPorId(int $id): Promesa;

    public function existenVigentesParaProducto(int $productoId): bool;
}
