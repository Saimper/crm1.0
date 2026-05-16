<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Infrastructure\Providers;

use App\Models\User;
use App\Modules\Usuarios\Domain\RolesCustom\Contracts\RepositorioRolCustom;
use App\Modules\Usuarios\Infrastructure\Http\Livewire\AdminEquiposProyecto;
use App\Modules\Usuarios\Infrastructure\Http\Livewire\AdminRolesCustom;
use App\Modules\Usuarios\Infrastructure\Http\Livewire\AdminUsuarios;
use App\Modules\Usuarios\Infrastructure\Http\Livewire\GestionUsuariosProyecto;
use App\Modules\Usuarios\Infrastructure\Http\Livewire\MatrizPermisos;
use App\Modules\Usuarios\Infrastructure\Http\Middleware\RequiereAdminGlobal;
use App\Modules\Usuarios\Infrastructure\Http\Middleware\RequiereAdminMandanteOGlobal;
use App\Modules\Usuarios\Infrastructure\Persistence\Repositories\RepositorioRolCustomEloquent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class UsuariosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RepositorioRolCustom::class, RepositorioRolCustomEloquent::class);
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
        Livewire::component('usuarios.matriz-permisos', MatrizPermisos::class);

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
}
