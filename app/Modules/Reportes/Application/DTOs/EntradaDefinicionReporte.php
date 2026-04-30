<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\DTOs;

final readonly class EntradaDefinicionReporte
{
    /**
     * @param  array<int, array{campo: string, etiqueta: string, agregacion?: ?string}>  $columnas
     * @param  array<int, array{campo: string, operador: string, valor?: mixed}>  $filtros
     * @param  list<string>  $agrupaciones
     * @param  array<int, array{campo: string, direccion?: string}>  $orden
     */
    public function __construct(
        public int $proyectoId,
        public string $codigo,
        public string $nombre,
        public string $entidadRaiz,
        public array $columnas,
        public array $filtros = [],
        public array $agrupaciones = [],
        public array $orden = [],
        public ?string $descripcion = null,
    ) {}

    /**
     * @return array{
     *     proyecto_id: int,
     *     codigo: string,
     *     nombre: string,
     *     descripcion: ?string,
     *     entidad_raiz: string,
     *     columnas: array<int, array{campo: string, etiqueta: string, agregacion?: ?string}>,
     *     filtros: array<int, array{campo: string, operador: string, valor?: mixed}>,
     *     agrupaciones: list<string>,
     *     orden: array<int, array{campo: string, direccion?: string}>
     * }
     */
    public function paraHidratacion(): array
    {
        return [
            'proyecto_id' => $this->proyectoId,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'entidad_raiz' => $this->entidadRaiz,
            'columnas' => $this->columnas,
            'filtros' => $this->filtros,
            'agrupaciones' => $this->agrupaciones,
            'orden' => $this->orden,
        ];
    }
}
