<?php

declare(strict_types=1);

namespace App\Modules\Gestiones\Domain\Contracts;

use App\Modules\Gestiones\Domain\Entities\Gestion;

interface GestionRepository
{
    public function save(Gestion $gestion): Gestion;
}
