<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Infrastructure\Providers;

use App\Models\User;
use App\Modules\Usuarios\Infrastructure\Http\Livewire\AdminEquiposProyecto;
use App\Modules\Usuarios\Infrastructure\Http\Livewire\AdminUsuarios;
use App\Modules\Usuarios\Infrastructure\Http\Livewire\GestionUsuariosProyecto;
use App\Modules\Usuarios\Infrastructure\Http\Middleware\RequiereAdminGlobal;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class UsuariosServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('admin.global', RequiereAdminGlobal::class);

        View::addNamespace('usuarios', resource_path('views/modules/usuarios'));
        Livewire::component('usuarios.admin-usuarios', AdminUsuarios::class);
        Livewire::component('usuarios.gestion-usuarios-proyecto', GestionUsuariosProyecto::class);
        Livewire::component('usuarios.admin-equipos-proyecto', AdminEquiposProyecto::class);

        Gate::before(function (User $user, string $ability, array $arguments): bool {
            if ($user->esAdminGlobal()) {
                return true;
            }

            $proyectoId = null;
            $carteraId = null;
            if (isset($arguments[0]) && is_int($arguments[0])) {
                $proyectoId = $arguments[0];
            }
            if (isset($arguments[1]) && is_int($arguments[1])) {
                $carteraId = $arguments[1];
            }

            return $user->tienePermiso($ability, $proyectoId, $carteraId);
        });
    }
}
