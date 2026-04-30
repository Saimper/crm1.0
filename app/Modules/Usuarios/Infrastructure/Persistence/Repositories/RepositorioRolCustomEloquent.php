<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Infrastructure\Persistence\Repositories;

use App\Modules\Usuarios\Domain\RolesCustom\Contracts\RepositorioRolCustom;
use App\Modules\Usuarios\Domain\RolesCustom\Entities\RolCustom;
use App\Modules\Usuarios\Domain\RolesCustom\ValueObjects\CodigoRolCustom;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RepositorioRolCustomEloquent implements RepositorioRolCustom
{
    public function existeCodigoEnProyecto(string $codigo, int $proyectoId, ?int $excluirId = null): bool
    {
        $q = DB::table('roles_custom')
            ->where('proyecto_id', $proyectoId)
            ->where('codigo', $codigo)
            ->whereNull('eliminada_en');

        if ($excluirId !== null) {
            $q->where('id', '!=', $excluirId);
        }

        return $q->exists();
    }

    public function buscarPorId(int $id): ?RolCustom
    {
        $row = DB::table('roles_custom')
            ->where('id', $id)
            ->whereNull('eliminada_en')
            ->first();

        if ($row === null) {
            return null;
        }

        return $this->hidratar($row);
    }

    public function buscarPorCodigo(string $codigo, int $proyectoId): ?RolCustom
    {
        $row = DB::table('roles_custom')
            ->where('codigo', $codigo)
            ->where('proyecto_id', $proyectoId)
            ->whereNull('eliminada_en')
            ->first();

        if ($row === null) {
            return null;
        }

        return $this->hidratar($row);
    }

    public function guardar(RolCustom $rol, int $usuarioCreadorId): int
    {
        return DB::transaction(function () use ($rol, $usuarioCreadorId): int {
            $id = (int) DB::table('roles_custom')->insertGetId([
                'public_id' => (string) Str::ulid(),
                'proyecto_id' => $rol->proyectoId,
                'codigo' => $rol->codigo->asString(),
                'nombre' => $rol->nombre,
                'descripcion' => $rol->descripcion,
                'activo' => $rol->activo,
                'creado_por_usuario_id' => $usuarioCreadorId,
            ]);

            $this->sincronizarPermisos($id, $rol->permisos);

            return $id;
        });
    }

    public function actualizar(RolCustom $rol): void
    {
        if ($rol->id === null) {
            throw new \LogicException('No se puede actualizar un rol custom sin id.');
        }

        $id = $rol->id;
        DB::transaction(function () use ($id, $rol): void {
            DB::table('roles_custom')
                ->where('id', $id)
                ->update([
                    'nombre' => $rol->nombre,
                    'descripcion' => $rol->descripcion,
                    'activo' => $rol->activo,
                ]);

            $this->sincronizarPermisos($id, $rol->permisos);
        });
    }

    public function eliminarLogico(int $id): void
    {
        DB::table('roles_custom')
            ->where('id', $id)
            ->update([
                'activo' => false,
                'eliminada_en' => now(),
            ]);
    }

    public function tieneAsignacionesActivas(int $id): bool
    {
        return DB::table('usuario_proyecto_rol_custom')
            ->where('rol_custom_id', $id)
            ->where('activo', true)
            ->exists();
    }

    public function asignarAUsuario(int $rolCustomId, int $usuarioId, int $proyectoId): void
    {
        DB::table('usuario_proyecto_rol_custom')->upsert(
            [[
                'usuario_id' => $usuarioId,
                'proyecto_id' => $proyectoId,
                'rol_custom_id' => $rolCustomId,
                'activo' => true,
            ]],
            ['usuario_id', 'proyecto_id', 'rol_custom_id'],
            ['activo'],
        );
    }

    public function revocarDeUsuario(int $rolCustomId, int $usuarioId, int $proyectoId): void
    {
        DB::table('usuario_proyecto_rol_custom')
            ->where('usuario_id', $usuarioId)
            ->where('proyecto_id', $proyectoId)
            ->where('rol_custom_id', $rolCustomId)
            ->delete();
    }

    /**
     * @param  list<string>  $codigosPermisos
     */
    private function sincronizarPermisos(int $rolCustomId, array $codigosPermisos): void
    {
        DB::table('rol_custom_permiso')
            ->where('rol_custom_id', $rolCustomId)
            ->delete();

        if ($codigosPermisos === []) {
            return;
        }

        $idsPermisos = DB::table('permisos')
            ->whereIn('codigo', $codigosPermisos)
            ->pluck('id', 'codigo');

        $filas = [];
        foreach ($codigosPermisos as $codigo) {
            $pid = $idsPermisos->get($codigo);
            if ($pid === null) {
                continue;
            }
            $filas[] = [
                'rol_custom_id' => $rolCustomId,
                'permiso_id' => (int) $pid,
            ];
        }

        if ($filas !== []) {
            DB::table('rol_custom_permiso')->insert($filas);
        }
    }

    private function hidratar(object $row): RolCustom
    {
        $permisos = DB::table('rol_custom_permiso as rcp')
            ->join('permisos as p', 'p.id', '=', 'rcp.permiso_id')
            ->where('rcp.rol_custom_id', (int) $row->id)
            ->pluck('p.codigo')
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();

        return RolCustom::reconstituir(
            id: (int) $row->id,
            proyectoId: (int) $row->proyecto_id,
            codigo: new CodigoRolCustom((string) $row->codigo),
            nombre: (string) $row->nombre,
            descripcion: $row->descripcion !== null ? (string) $row->descripcion : null,
            activo: (bool) $row->activo,
            permisos: $permisos,
        );
    }
}
