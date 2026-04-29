<?php

declare(strict_types=1);

namespace App\Modules\Casos\Domain\Contracts;

use App\Modules\Casos\Domain\Entities\Caso;

interface CasoRepository
{
    public function save(Caso $caso): Caso;

    public function buscarPorId(int $id): Caso;
}
