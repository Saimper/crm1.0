<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\UseCases;

use App\Modules\Reportes\Domain\Constructor\Contracts\RepositorioDefinicionReporte;
use DomainException;

final class EliminarDefinicionReporte
{
    public function __construct(
        private readonly RepositorioDefinicionReporte $repositorio,
    ) {}

    public function execute(int $id, int $proyectoId): void
    {
        $existente = $this->repositorio->buscar($id, $proyectoId);
        if ($existente === null) {
            throw new DomainException("Definición {$id} no encontrada en proyecto {$proyectoId}.");
        }

        $this->repositorio->eliminar($id);
    }
}
