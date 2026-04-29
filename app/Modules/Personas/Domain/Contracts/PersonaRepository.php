<?php

declare(strict_types=1);

namespace App\Modules\Personas\Domain\Contracts;

use App\Modules\Personas\Domain\Entities\Persona;
use App\Modules\Personas\Domain\ValueObjects\Identificacion;

interface PersonaRepository
{
    public function save(Persona $persona): Persona;

    public function existePorIdentificacionEnProyecto(
        int $proyectoId,
        int $tipoIdentificacionId,
        Identificacion $identificacion,
    ): bool;
}
