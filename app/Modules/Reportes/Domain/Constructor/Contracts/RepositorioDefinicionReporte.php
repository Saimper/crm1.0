<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Domain\Constructor\Contracts;

use App\Modules\Reportes\Domain\Constructor\Entities\DefinicionReporte;

interface RepositorioDefinicionReporte
{
    public function guardar(DefinicionReporte $def, int $usuarioId): int;

    public function actualizar(int $id, DefinicionReporte $def): void;

    public function eliminar(int $id): void;

    /**
     * @return array{
     *     id: int,
     *     proyecto_id: int,
     *     codigo: string,
     *     nombre: string,
     *     descripcion: ?string,
     *     entidad_raiz: string,
     *     columnas: array<int,array<string,mixed>>,
     *     filtros: array<int,array<string,mixed>>,
     *     agrupaciones: list<string>,
     *     orden: array<int,array<string,mixed>>,
     *     activo: bool,
     * }|null
     */
    public function buscar(int $id, int $proyectoId): ?array;

    /**
     * @return list<array<string,mixed>>
     */
    public function listarPorProyecto(int $proyectoId, bool $soloActivos = true): array;

    public function existeCodigo(int $proyectoId, string $codigo, ?int $excluirId = null): bool;
}
