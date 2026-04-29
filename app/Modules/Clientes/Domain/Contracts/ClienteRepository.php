<?php

declare(strict_types=1);

namespace App\Modules\Clientes\Domain\Contracts;

use App\Modules\Clientes\Domain\Entities\Cliente;
use App\Modules\Clientes\Domain\ValueObjects\Identificacion;

interface ClienteRepository
{
    public function save(Cliente $cliente): Cliente;

    public function existePorIdentificacion(Identificacion $identificacion): bool;
}
