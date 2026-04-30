<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\UseCases;

use App\Modules\Reportes\Application\DTOs\EntradaDefinicionReporte;
use App\Modules\Reportes\Application\Hidratacion\HidratadorDefinicionReporte;
use App\Modules\Reportes\Application\Servicios\ServicioCamposPersonalizadosReporte;
use App\Modules\Reportes\Domain\Constructor\Catalogo\CatalogoCamposReporte;
use App\Modules\Reportes\Domain\Constructor\Contracts\RepositorioDefinicionReporte;
use DomainException;

final class ActualizarDefinicionReporte
{
    public function __construct(
        private readonly RepositorioDefinicionReporte $repositorio,
        private readonly ServicioCamposPersonalizadosReporte $serviciCp,
    ) {}

    public function execute(int $id, EntradaDefinicionReporte $entrada): void
    {
        $def = HidratadorDefinicionReporte::desdeArray($entrada->paraHidratacion());

        $catalogo = new CatalogoCamposReporte(
            $def->entidad,
            $this->serviciCp->obtenerCampos($def->entidad, $def->proyectoId),
        );

        $def->validar($catalogo);

        $existente = $this->repositorio->buscar($id, $def->proyectoId);
        if ($existente === null) {
            throw new DomainException("Definición de reporte {$id} no encontrada en proyecto {$def->proyectoId}.");
        }

        if ($this->repositorio->existeCodigo($def->proyectoId, $def->codigo, $id)) {
            throw new DomainException("Ya existe otra definición con código '{$def->codigo}' en el proyecto.");
        }

        $this->repositorio->actualizar($id, $def);
    }
}
