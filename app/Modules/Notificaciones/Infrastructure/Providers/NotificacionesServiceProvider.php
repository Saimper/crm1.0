<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Infrastructure\Providers;

use App\Modules\Compromisos\Domain\Events\CompromisoRoto;
use App\Modules\Notificaciones\Application\Console\Commands\GenerarNotificacionesCommand;
use App\Modules\Notificaciones\Application\Listeners\NotificarCompromisoRoto;
use App\Modules\Notificaciones\Infrastructure\Http\Livewire\BadgeNotificaciones;
use App\Modules\Notificaciones\Infrastructure\Http\Livewire\ListadoNotificaciones;
use Illuminate\Support\Facades\Event;
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

        // Romper un compromiso genera la llamada de vuelta. Se escucha el evento
        // de dominio y no se llama desde el comando, para que valga igual cuando
        // lo rompe la tarea diaria y cuando lo marca un supervisor a mano (§3:
        // entre módulos, sólo eventos y contratos).
        Event::listen(CompromisoRoto::class, NotificarCompromisoRoto::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerarNotificacionesCommand::class,
            ]);
        }
    }
}
