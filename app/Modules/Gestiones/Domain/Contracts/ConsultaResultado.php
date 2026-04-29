<?php

declare(strict_types=1);

namespace App\Modules\Gestiones\Domain\Contracts;

use App\Modules\Gestiones\Domain\ValueObjects\BanderasResultado;

interface ConsultaResultado
{
    public function banderas(int $resultadoId): BanderasResultado;
}
