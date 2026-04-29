<?php

declare(strict_types=1);

namespace App\Modules\Asignaciones\Domain\Contracts;

use App\Modules\Asignaciones\Domain\Entities\Asignacion;

interface AsignacionRepository
{
    public function save(Asignacion $asignacion): Asignacion;

    public function buscarPorId(int $id): Asignacion;

    public function existeParaCampanaCaso(int $campanaId, int $casoId): bool;
}
