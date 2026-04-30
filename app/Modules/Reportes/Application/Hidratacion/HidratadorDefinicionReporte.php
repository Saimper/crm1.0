<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\Hidratacion;

use App\Modules\Reportes\Domain\Constructor\Entities\DefinicionReporte;
use App\Modules\Reportes\Domain\Constructor\Enums\EntidadRaiz;
use App\Modules\Reportes\Domain\Constructor\ValueObjects\ColumnaReporte;
use App\Modules\Reportes\Domain\Constructor\ValueObjects\FiltroReporte;
use App\Modules\Reportes\Domain\Constructor\ValueObjects\OrdenReporte;

final class HidratadorDefinicionReporte
{
    /**
     * @param  array{
     *     proyecto_id: int,
     *     codigo: string,
     *     nombre: string,
     *     descripcion?: ?string,
     *     entidad_raiz: string,
     *     columnas: array<int, array{campo: string, etiqueta: string, agregacion?: ?string}>,
     *     filtros: array<int, array{campo: string, operador: string, valor?: mixed}>,
     *     agrupaciones: list<string>,
     *     orden: array<int, array{campo: string, direccion?: string}>
     * }  $data
     */
    public static function desdeArray(array $data): DefinicionReporte
    {
        return new DefinicionReporte(
            proyectoId: (int) $data['proyecto_id'],
            codigo: $data['codigo'],
            nombre: $data['nombre'],
            entidad: EntidadRaiz::from($data['entidad_raiz']),
            columnas: array_map(
                static fn (array $c): ColumnaReporte => ColumnaReporte::fromArray($c),
                $data['columnas'],
            ),
            filtros: array_map(
                static fn (array $f): FiltroReporte => FiltroReporte::fromArray($f),
                $data['filtros'],
            ),
            agrupaciones: $data['agrupaciones'],
            orden: array_map(
                static fn (array $o): OrdenReporte => OrdenReporte::fromArray($o),
                $data['orden'],
            ),
            descripcion: $data['descripcion'] ?? null,
        );
    }
}
