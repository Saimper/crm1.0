<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Infrastructure\Providers;

use App\Models\User;
use App\Modules\Usuarios\Domain\Contracts\AccesoACuenta;
use App\Modules\Usuarios\Domain\Contracts\AccesoAReparto;
use App\Modules\Usuarios\Domain\RolesCustom\Contracts\RepositorioRolCustom;
use App\Modules\Usuarios\Infrastructure\Http\Livewire\AdminEquiposProyecto;
use App\Modules\Usuarios\Infrastructure\Http\Livewire\AdminRolesBase;
use App\Modules\Usuarios\Infrastructure\Http\Livewire\AdminRolesCustom;
use App\Modules\Usuarios\Infrastructure\Http\Livewire\AdminUsuarios;
use App\Modules\Usuarios\Infrastructure\Http\Livewire\GestionUsuariosProyecto;
use App\Modules\Usuarios\Infrastructure\Http\Livewire\MatrizPermisos;
use App\Modules\Usuarios\Infrastructure\Http\Middleware\RequiereAdminGlobal;
use App\Modules\Usuarios\Infrastructure\Http\Middleware\RequiereAdminMandanteOGlobal;
use App\Modules\Usuarios\Infrastructure\Persistence\Repositories\AccesoACuentaEloquent;
use App\Modules\Usuarios\Infrastructure\Persistence\Repositories\AccesoARepartoEloquent;
use App\Modules\Usuarios\Infrastructure\Persistence\Repositories\RepositorioRolCustomEloquent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class UsuariosServiceProvider extends ServiceProvider
{
    /**
     * Las tablas de las que depende `User::tienePermiso()` en cualquiera de sus
     * cuatro rutas (base, cartera, custom, mandante) más `esAdminGlobal()`.
     * Una escritura en cualquiera de ellas invalida el memo de permisos.
     *
     * `proyectos` está porque la ruta F38 cruza `proyectos.mandante_id`:
     * mover un proyecto de mandante, o darlo de baja, cambia quién tiene
     * permiso en él sin que se toque ninguna tabla de roles.
     */
    private const TABLAS_DE_PERMISOS = [
        'usuario_proyecto_rol',
        'usuario_proyecto_rol_cartera',
        'usuario_proyecto_rol_custom',
        'usuario_global_rol',
        'usuario_mandante_rol',
        'rol_permiso',
        'rol_proyecto_permiso',
        'rol_custom_permiso',
        'roles_custom',
        'roles',
        'permisos',
        'proyectos',
    ];

    public function register(): void
    {
        $this->app->bind(RepositorioRolCustom::class, RepositorioRolCustomEloquent::class);
        $this->app->bind(AccesoACuenta::class, AccesoACuentaEloquent::class);
        $this->app->bind(AccesoAReparto::class, AccesoARepartoEloquent::class);
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('admin.global', RequiereAdminGlobal::class);
        $router->aliasMiddleware('admin.dual', RequiereAdminMandanteOGlobal::class);

        View::addNamespace('usuarios', resource_path('views/modules/usuarios'));
        Livewire::component('usuarios.admin-usuarios', AdminUsuarios::class);
        Livewire::component('usuarios.gestion-usuarios-proyecto', GestionUsuariosProyecto::class);
        Livewire::component('usuarios.admin-equipos-proyecto', AdminEquiposProyecto::class);
        Livewire::component('usuarios.admin-roles-custom', AdminRolesCustom::class);
        Livewire::component('usuarios.admin-roles-base', AdminRolesBase::class);
        Livewire::component('usuarios.matriz-permisos', MatrizPermisos::class);

        $this->invalidarMemoDePermisos();

        Gate::before(function (User $user, string $ability, array $arguments): bool {
            if ($user->esAdminGlobal()) {
                return true;
            }

            $proyectoId = null;
            $carteraId = null;
            if (isset($arguments[0])) {
                if (is_int($arguments[0])) {
                    $proyectoId = $arguments[0];
                } elseif ($arguments[0] instanceof Model) {
                    $proyectoId = (int) $arguments[0]->getKey();
                }
            }
            if (isset($arguments[1])) {
                if (is_int($arguments[1])) {
                    $carteraId = $arguments[1];
                } elseif ($arguments[1] instanceof Model) {
                    $carteraId = (int) $arguments[1]->getKey();
                }
            }

            return $user->tienePermiso($ability, $proyectoId, $carteraId);
        });
    }

    /**
     * Cuándo se tira el memo de permisos que `User` guarda por instancia.
     *
     * El memo quita las 22 consultas del menú lateral y las 13 del dashboard
     * que se medían en cada carga de página (ver User::$memoPermisos). El
     * precio es decidir cuándo deja de valer, y son dos momentos:
     *
     *  (a) Cada petición HTTP. `Kernel::handle` rebindea `request` en el
     *      contenedor —`app->instance('request')`— y eso dispara los callbacks
     *      de `rebinding`. También lo hace cada `$this->get()` de un test, así
     *      que ningún test arrastra memos de una petición a la siguiente. Con
     *      Octane o un worker de cola el proceso vive muchas peticiones, y sin
     *      esto un permiso quitado a media mañana seguiría concedido hasta
     *      reiniciar.
     *
     *  (b) Cada escritura de ESTE proceso en una tabla de roles o permisos.
     *      Cubre a quien cambia un rol y comprueba el permiso sobre la misma
     *      instancia sin petición entre medias, que es lo que hacen decenas de
     *      tests y algún comando. El filtro es un preg_match sobre el verbo
     *      (falla rápido para todo SELECT) y otro sobre los nombres de tabla;
     *      no se parsea SQL.
     *
     * Salvedad: `DB::listen` registra el listener en el dispatcher de eventos de
     * la conexión. Un test que llame a `Event::fake()` SIN lista sustituye el
     * dispatcher de la aplicación; las conexiones ya abiertas conservan el
     * suyo, pero una conexión creada DESPUÉS del fake nacería con el falso, no
     * dispararía `QueryExecuted`, y la invalidación por escritura no ocurriría
     * en ese test. Con lista (`Event::fake([X::class])`) no pasa: los eventos
     * no listados siguen llegando. Si alguna vez hace falta, está
     * `User::olvidarPermisosCacheados()`.
     */
    private function invalidarMemoDePermisos(): void
    {
        $this->app->rebinding('request', static function (): void {
            User::olvidarPermisosCacheados();
        });

        $tablas = '/\b(?:'.implode('|', self::TABLAS_DE_PERMISOS).')\b/i';

        DB::listen(static function (QueryExecuted $consulta) use ($tablas): void {
            if (preg_match('/^\s*(?:insert|update|delete|replace|truncate)\b/i', $consulta->sql) !== 1) {
                return;
            }

            if (preg_match($tablas, $consulta->sql) === 1) {
                User::olvidarPermisosCacheados();
            }
        });
    }
}
