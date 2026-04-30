<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Infrastructure\Providers;

use App\Modules\Notificaciones\Application\Console\Commands\GenerarNotificacionesCommand;
use App\Modules\Notificaciones\Infrastructure\Http\Livewire\BadgeNotificaciones;
use App\Modules\Notificaciones\Infrastructure\Http\Livewire\ListadoNotificaciones;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class NotificacionesServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        View::addNamespace('notificaciones', resource_path('views/modules/notificaciones'));
        Livewire::component('notificaciones.listado-notificaciones', ListadoNotificaciones::class);
        Livewire::component('notificaciones.badge-notificaciones', BadgeNotificaciones::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerarNotificacionesCommand::class,
            ]);
        }
    }
}
