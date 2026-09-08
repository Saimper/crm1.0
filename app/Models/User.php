<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Usuarios\Infrastructure\Persistence\Models\RolModel;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'activo',
        'locale',
        'mandante_origen_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Memo de permisos de ESTA instancia.
     *
     * Medido en la auditoría de septiembre: pintar los 6 enlaces del menú
     * lateral costaba 22 consultas, las tarjetas del dashboard 13 más, en cada
     * carga de cada página; y un `@can` dentro de un `@foreach` lo multiplicaba
     * por filas. Cada `@can` pasa por `Gate::before`, que llama a
     * `esAdminGlobal()` y a `tienePermiso()`, y las dos iban a la base todas
     * las veces con los mismos argumentos.
     *
     * Es por instancia y no global porque el resultado depende de quién
     * pregunta, y una petición sólo tiene un usuario autenticado. La clave
     * lleva el proyecto YA RESUELTO (ver tienePermiso): `Gate::before` pasa
     * `null` en todo `@can`/`can:` sin argumentos, y hay tests que cambian el
     * proyecto activo sobre la misma instancia sin petición HTTP entre medias;
     * si la clave llevara el `null` literal, la respuesta del proyecto A se
     * serviría para el B.
     *
     * @var array<string, bool>
     */
    private array $memoPermisos = [];

    /** @var array<string, list<int>|null> Carteras permitidas por proyecto, mismo ciclo de vida que. */
    private array $memoCarteras = [];

    /**
     * Memoizado aparte porque no depende del proyecto, y porque `Gate::before`
     * lo consulta ANTES de cada `tienePermiso()`: sin esto cada `@can` seguiría
     * costando una consulta aunque el permiso estuviera cacheado.
     */
    private ?bool $memoAdminGlobal = null;

    /**
     * Generación con la que se llenó el memo de esta instancia. Si no coincide
     * con la estática, el memo está viejo y se vacía antes de leerlo.
     */
    private int $memoGeneracion = 0;

    /**
     * Generación vigente para TODAS las instancias del proceso. La sube
     * `olvidarPermisosCacheados()`: al rebindear la petición y cuando este
     * proceso escribe en una tabla de roles o permisos
     * (UsuariosServiceProvider). Es un contador y no un «vaciar todo» porque
     * las instancias no están registradas en ningún sitio: cada una compara su
     * generación al usarse, sin que nadie tenga que recorrerlas.
     */
    private static int $generacion = 0;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    /**
     * Invalida el memo de permisos de todas las instancias vivas.
     *
     * Quien cambia roles o permisos por un camino que no pasa por la base de
     * este proceso (otra conexión, un servicio externo) y necesita que la
     * instancia que ya tiene en la mano lo vea, llama aquí.
     */
    public static function olvidarPermisosCacheados(): void
    {
        self::$generacion++;
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
        $this->descartarMemoViejo();

        return $this->memoAdminGlobal ??= DB::table('usuario_global_rol as ugr')
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

        $tieneRolEnProyecto = DB::table('usuario_proyecto_rol')
            ->where('usuario_id', $this->id)
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->exists();

        if ($tieneRolEnProyecto) {
            return true;
        }

        // F38: rol mandante autoriza cross-proyecto del mandante.
        return DB::table('usuario_mandante_rol as umr')
            ->join('proyectos as p', 'p.mandante_id', '=', 'umr.mandante_id')
            ->where('umr.usuario_id', $this->id)
            ->where('umr.activo', true)
            ->where('p.id', $proyectoId)
            ->exists();
    }

    /** @return array<int, int>  IDs de mandantes donde el usuario tiene rol mandante activo. */
    public function mandantesAdministrados(): array
    {
        return DB::table('usuario_mandante_rol')
            ->where('usuario_id', $this->id)
            ->where('activo', true)
            ->distinct()
            ->pluck('mandante_id')
            ->map(fn (mixed $v): int => (int) $v)
            ->all();
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
     *
     * La evaluación de verdad está en `evaluarPermiso()`, sin cambios; esto
     * sólo la envuelve en el memo. La clave se construye DESPUÉS de resolver el
     * proyecto activo, por lo que se explica en `$memoPermisos`.
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

        $clave = $codigo.'|'.$proyectoId.'|'.($carteraId ?? '-');

        // esAdminGlobal() ya descartó el memo viejo en esta misma llamada.
        if (array_key_exists($clave, $this->memoPermisos)) {
            return $this->memoPermisos[$clave];
        }

        return $this->memoPermisos[$clave] = $this->evaluarPermiso($codigo, $proyectoId, $carteraId);
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

    /**
     * Las cuatro rutas de evaluación, en el orden de siempre: rol base con
     * cartera-scoping (F22), rol custom (F33) y rol de mandante (F38). Admin
     * global se resolvió antes de llegar aquí.
     */
    private function evaluarPermiso(string $codigo, int $proyectoId, ?int $carteraId): bool
    {
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

        if ($tienePermisoCustom) {
            return true;
        }

        // F38: rol mandante (ADMIN_MANDANTE) autoriza cross-proyecto si el
        // proyecto pertenece al mandante donde el user tiene el rol. Sin
        // cartera-scoping (mandante-scope cubre todo el alcance del mandante).
        return DB::table('usuario_mandante_rol as umr')
            ->join('roles as r', 'r.id', '=', 'umr.rol_id')
            ->join('rol_permiso as rp', 'rp.rol_id', '=', 'umr.rol_id')
            ->join('permisos as p', 'p.id', '=', 'rp.permiso_id')
            ->join('proyectos as pr', 'pr.mandante_id', '=', 'umr.mandante_id')
            ->where('umr.usuario_id', $this->id)
            ->where('umr.activo', true)
            ->where('r.activo', true)
            ->where('pr.id', $proyectoId)
            ->where('p.codigo', $codigo)
            ->where('p.activo', true)
            ->exists();
    }

    /**
     * Las carteras a las que este usuario está limitado en un proyecto, o null
     * si no tiene límite.
     *
     * Es la otra mitad del cartera-scoping de F22. `tienePermiso()` sólo mira
     * la restricción cuando le pasan una cartera, así que un supervisor
     * limitado a la cartera A tenía 403 al pedir `?cartera=B`… y la cartera
     * entera del proyecto con sólo no poner el filtro. El listado y las
     * exportaciones recortan con esto ANTES de aplicar cualquier filtro.
     *
     * Misma regla que la evaluación del permiso: basta un rol base sin filas en
     * `usuario_proyecto_rol_cartera` para no tener límite; un rol custom (F33)
     * o de mandante (F38) tampoco lo tienen; con todos los roles base
     * restringidos, el límite es la unión de sus carteras. ADMIN_GLOBAL, nunca.
     *
     * @return list<int>|null
     */
    public function carterasPermitidas(int $proyectoId): ?array
    {
        if ($this->esAdminGlobal()) {
            return null;
        }

        $clave = 'carteras|'.$proyectoId;
        if (array_key_exists($clave, $this->memoCarteras)) {
            return $this->memoCarteras[$clave];
        }

        return $this->memoCarteras[$clave] = $this->evaluarCarteras($proyectoId);
    }

    /** @return list<int>|null */
    private function evaluarCarteras(int $proyectoId): ?array
    {
        $rolesBase = DB::table('usuario_proyecto_rol as upr')
            ->join('roles as r', 'r.id', '=', 'upr.rol_id')
            ->where('upr.usuario_id', $this->id)
            ->where('upr.proyecto_id', $proyectoId)
            ->where('upr.activo', true)
            ->where('r.activo', true)
            ->pluck('upr.rol_id')
            ->map(fn (mixed $v): int => (int) $v)
            ->unique()
            ->values()
            ->all();

        $restringidos = DB::table('usuario_proyecto_rol_cartera')
            ->where('usuario_id', $this->id)
            ->where('proyecto_id', $proyectoId)
            ->whereIn('rol_id', $rolesBase)
            ->get(['rol_id', 'cartera_id']);

        $rolesConLimite = $restringidos->pluck('rol_id')->map(fn (mixed $v): int => (int) $v)->unique()->all();

        foreach ($rolesBase as $rolId) {
            if (! in_array($rolId, $rolesConLimite, true)) {
                return null;
            }
        }

        $tieneRolSinCartera = DB::table('usuario_proyecto_rol_custom as uprc')
            ->join('roles_custom as rc', 'rc.id', '=', 'uprc.rol_custom_id')
            ->where('uprc.usuario_id', $this->id)
            ->where('uprc.proyecto_id', $proyectoId)
            ->where('uprc.activo', true)
            ->where('rc.activo', true)
            ->whereNull('rc.eliminada_en')
            ->exists()
            || DB::table('usuario_mandante_rol as umr')
                ->join('proyectos as pr', 'pr.mandante_id', '=', 'umr.mandante_id')
                ->where('umr.usuario_id', $this->id)
                ->where('umr.activo', true)
                ->where('pr.id', $proyectoId)
                ->exists();

        if ($tieneRolSinCartera || $rolesBase === []) {
            return null;
        }

        return $restringidos->pluck('cartera_id')->map(fn (mixed $v): int => (int) $v)->unique()->values()->all();
    }

    /**
     * Si alguien subió la generación desde que se llenó el memo, lo que hay
     * dentro puede estar mal: se tira entero. Comparar un entero es más barato
     * que cualquier consulta que se quiera ahorrar.
     */
    private function descartarMemoViejo(): void
    {
        if ($this->memoGeneracion === self::$generacion) {
            return;
        }

        $this->memoPermisos = [];
        $this->memoCarteras = [];
        $this->memoAdminGlobal = null;
        $this->memoGeneracion = self::$generacion;
    }
}
