<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\UseCases;

use App\Modules\Reportes\Application\DTOs\EntradaDefinicionReporte;
use App\Modules\Reportes\Application\Hidratacion\HidratadorDefinicionReporte;
use App\Modules\Reportes\Application\Servicios\ServicioCamposPersonalizadosReporte;
use App\Modules\Reportes\Domain\Constructor\Catalogo\CatalogoCamposReporte;
use App\Modules\Reportes\Domain\Constructor\Contracts\RepositorioDefinicionReporte;
use DomainException;

final class CrearDefinicionReporte
{
    public function __construct(
        private readonly RepositorioDefinicionReporte $repositorio,
        private readonly ServicioCamposPersonalizadosReporte $serviciCp,
    ) {}

    public function execute(EntradaDefinicionReporte $entrada, int $usuarioId): int
    {
        $def = HidratadorDefinicionReporte::desdeArray($entrada->paraHidratacion());

        $catalogo = new CatalogoCamposReporte(
            $def->entidad,
            $this->serviciCp->obtenerCampos($def->entidad, $def->proyectoId),
        );

        $def->validar($catalogo);

        if ($this->repositorio->existeCodigo($def->proyectoId, $def->codigo)) {
            throw new DomainException("Ya existe una definición con código '{$def->codigo}' en el proyecto.");
        }

        return $this->repositorio->guardar($def, $usuarioId);
    }
}
