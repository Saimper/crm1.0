<?php

namespace App\Models;

use App\Modules\Usuarios\Infrastructure\Persistence\Models\RolModel;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'activo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    /** Roles globales (ADMIN_GLOBAL u otros sin scope de proyecto). */
    public function rolesGlobales(): BelongsToMany
    {
        return $this->belongsToMany(
            RolModel::class,
            'usuario_global_rol',
            'usuario_id',
            'rol_id',
        );
    }

    public function esAdminGlobal(): bool
    {
        return DB::table('usuario_global_rol as ugr')
            ->join('roles as r', 'r.id', '=', 'ugr.rol_id')
            ->where('ugr.usuario_id', $this->id)
            ->where('r.codigo', 'ADMIN_GLOBAL')
            ->where('r.activo', true)
            ->exists();
    }

    /** @return array<int, int>  IDs de proyectos donde el usuario tiene asignación activa. */
    public function proyectosAsignados(): array
    {
        return DB::table('usuario_proyecto_rol as upr')
            ->join('proyectos as p', 'p.id', '=', 'upr.proyecto_id')
            ->where('upr.usuario_id', $this->id)
            ->where('upr.activo', true)
            ->where('p.activo', true)
            ->whereNull('p.eliminada_en')
            ->distinct()
            ->pluck('upr.proyecto_id')
            ->map(fn (mixed $v): int => (int) $v)
            ->all();
    }

    public function tieneAccesoAProyecto(int $proyectoId): bool
    {
        if ($this->esAdminGlobal()) {
            return true;
        }

        return DB::table('usuario_proyecto_rol')
            ->where('usuario_id', $this->id)
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->exists();
    }

    /**
     * Evalúa un permiso en el contexto del proyecto dado (o el activo si es null).
     *
     * Scope por cartera (Fase 22):
     *   - Si $carteraId es NULL → se evalúa solo a nivel proyecto (comportamiento legacy).
     *   - Si $carteraId tiene valor → se permite si:
     *       a) El rol del usuario no tiene restricción de cartera (no hay filas en
     *          `usuario_proyecto_rol_cartera` para ese rol), o
     *       b) Tiene restricción y la cartera solicitada está en la lista permitida.
     */
    public function tienePermiso(string $codigo, ?int $proyectoId = null, ?int $carteraId = null): bool
    {
        if ($this->esAdminGlobal()) {
            return true;
        }

        $proyectoId ??= app()->bound('tenancy.proyecto_activo')
            ? (int) app('tenancy.proyecto_activo')->id
            : null;

        if ($proyectoId === null) {
            return false;
        }

        // Roles base — pasan por la matriz F22 (cartera-scoping aplicable).
        $rolesConPermiso = DB::table('usuario_proyecto_rol as upr')
            ->join('roles as r', 'r.id', '=', 'upr.rol_id')
            ->join('rol_permiso as rp', 'rp.rol_id', '=', 'upr.rol_id')
            ->join('permisos as p', 'p.id', '=', 'rp.permiso_id')
            ->where('upr.usuario_id', $this->id)
            ->where('upr.proyecto_id', $proyectoId)
            ->where('upr.activo', true)
            ->where('r.activo', true)
            ->where('p.codigo', $codigo)
            ->where('p.activo', true)
            ->pluck('upr.rol_id')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        if ($rolesConPermiso !== []) {
            if ($carteraId === null) {
                return true;
            }

            foreach ($rolesConPermiso as $rolId) {
                $tieneRestriccion = DB::table('usuario_proyecto_rol_cartera')
                    ->where('usuario_id', $this->id)
                    ->where('proyecto_id', $proyectoId)
                    ->where('rol_id', $rolId)
                    ->exists();

                if (! $tieneRestriccion) {
                    return true;
                }

                $autorizaCartera = DB::table('usuario_proyecto_rol_cartera')
                    ->where('usuario_id', $this->id)
                    ->where('proyecto_id', $proyectoId)
                    ->where('rol_id', $rolId)
                    ->where('cartera_id', $carteraId)
                    ->exists();

                if ($autorizaCartera) {
                    return true;
                }
            }
        }

        // Roles custom F33 — siempre aplican a todo el proyecto (sin cartera-scoping en F33).
        $tienePermisoCustom = DB::table('usuario_proyecto_rol_custom as uprc')
            ->join('roles_custom as rc', 'rc.id', '=', 'uprc.rol_custom_id')
            ->join('rol_custom_permiso as rcp', 'rcp.rol_custom_id', '=', 'uprc.rol_custom_id')
            ->join('permisos as p', 'p.id', '=', 'rcp.permiso_id')
            ->where('uprc.usuario_id', $this->id)
            ->where('uprc.proyecto_id', $proyectoId)
            ->where('uprc.activo', true)
            ->where('rc.activo', true)
            ->whereNull('rc.eliminada_en')
            ->where('p.codigo', $codigo)
            ->where('p.activo', true)
            ->exists();

        return $tienePermisoCustom;
    }

    public function tieneRolEnProyecto(string $rolCodigo, int $proyectoId): bool
    {
        if ($this->esAdminGlobal()) {
            return true;
        }

        return DB::table('usuario_proyecto_rol as upr')
            ->join('roles as r', 'r.id', '=', 'upr.rol_id')
            ->where('upr.usuario_id', $this->id)
            ->where('upr.proyecto_id', $proyectoId)
            ->where('upr.activo', true)
            ->where('r.codigo', $rolCodigo)
            ->where('r.activo', true)
            ->exists();
    }
}
