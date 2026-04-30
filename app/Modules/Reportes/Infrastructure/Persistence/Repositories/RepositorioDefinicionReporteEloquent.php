<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Persistence\Repositories;

use App\Modules\Reportes\Domain\Constructor\Contracts\RepositorioDefinicionReporte;
use App\Modules\Reportes\Domain\Constructor\Entities\DefinicionReporte;
use App\Modules\Reportes\Infrastructure\Persistence\Models\DefinicionReporteModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RepositorioDefinicionReporteEloquent implements RepositorioDefinicionReporte
{
    public function guardar(DefinicionReporte $def, int $usuarioId): int
    {
        return (int) DB::table('reportes_definiciones')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $def->proyectoId,
            'codigo' => $def->codigo,
            'nombre' => $def->nombre,
            'descripcion' => $def->descripcion,
            'entidad_raiz' => $def->entidad->value,
            'columnas' => json_encode(array_map(fn ($c) => $c->toArray(), $def->columnas), JSON_THROW_ON_ERROR),
            'filtros' => json_encode(array_map(fn ($f) => $f->toArray(), $def->filtros), JSON_THROW_ON_ERROR),
            'agrupaciones' => json_encode($def->agrupaciones, JSON_THROW_ON_ERROR),
            'orden' => json_encode(array_map(fn ($o) => $o->toArray(), $def->orden), JSON_THROW_ON_ERROR),
            'activo' => true,
            'creado_por_usuario_id' => $usuarioId,
        ]);
    }

    public function actualizar(int $id, DefinicionReporte $def): void
    {
        DB::table('reportes_definiciones')
            ->where('id', $id)
            ->where('proyecto_id', $def->proyectoId)
            ->update([
                'codigo' => $def->codigo,
                'nombre' => $def->nombre,
                'descripcion' => $def->descripcion,
                'entidad_raiz' => $def->entidad->value,
                'columnas' => json_encode(array_map(fn ($c) => $c->toArray(), $def->columnas), JSON_THROW_ON_ERROR),
                'filtros' => json_encode(array_map(fn ($f) => $f->toArray(), $def->filtros), JSON_THROW_ON_ERROR),
                'agrupaciones' => json_encode($def->agrupaciones, JSON_THROW_ON_ERROR),
                'orden' => json_encode(array_map(fn ($o) => $o->toArray(), $def->orden), JSON_THROW_ON_ERROR),
            ]);
    }

    public function eliminar(int $id): void
    {
        DB::table('reportes_definiciones')
            ->where('id', $id)
            ->update(['activo' => false, 'eliminada_en' => now()]);
    }

    public function buscar(int $id, int $proyectoId): ?array
    {
        $row = DB::table('reportes_definiciones')
            ->where('id', $id)
            ->where('proyecto_id', $proyectoId)
            ->whereNull('eliminada_en')
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'proyecto_id' => (int) $row->proyecto_id,
            'codigo' => (string) $row->codigo,
            'nombre' => (string) $row->nombre,
            'descripcion' => $row->descripcion !== null ? (string) $row->descripcion : null,
            'entidad_raiz' => (string) $row->entidad_raiz,
            'columnas' => json_decode((string) $row->columnas, true, 512, JSON_THROW_ON_ERROR),
            'filtros' => json_decode((string) $row->filtros, true, 512, JSON_THROW_ON_ERROR),
            'agrupaciones' => json_decode((string) $row->agrupaciones, true, 512, JSON_THROW_ON_ERROR),
            'orden' => json_decode((string) $row->orden, true, 512, JSON_THROW_ON_ERROR),
            'activo' => (bool) $row->activo,
        ];
    }

    public function listarPorProyecto(int $proyectoId, bool $soloActivos = true): array
    {
        $q = DB::table('reportes_definiciones')
            ->where('proyecto_id', $proyectoId)
            ->whereNull('eliminada_en');

        if ($soloActivos) {
            $q->where('activo', true);
        }

        $rows = $q->orderBy('nombre')->get();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) $r->id,
                'public_id' => (string) $r->public_id,
                'codigo' => (string) $r->codigo,
                'nombre' => (string) $r->nombre,
                'descripcion' => $r->descripcion !== null ? (string) $r->descripcion : null,
                'entidad_raiz' => (string) $r->entidad_raiz,
                'activo' => (bool) $r->activo,
                'creada_en' => (string) $r->creada_en,
            ];
        }

        return $out;
    }

    public function existeCodigo(int $proyectoId, string $codigo, ?int $excluirId = null): bool
    {
        $q = DB::table('reportes_definiciones')
            ->where('proyecto_id', $proyectoId)
            ->where('codigo', $codigo);

        if ($excluirId !== null) {
            $q->where('id', '!=', $excluirId);
        }

        return $q->exists();
    }

    public function modelo(): string
    {
        return DefinicionReporteModel::class;
    }
}
