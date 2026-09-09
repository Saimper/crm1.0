<?php

declare(strict_types=1);

namespace App\Modules\Contactos\Infrastructure\Providers;

use App\Modules\Contactos\Application\Console\Commands\ExtraerContactosDeCamposCommand;
use App\Modules\Contactos\Domain\Contracts\AltaContactosEnLote;
use App\Modules\Contactos\Domain\Contracts\ContactoRepository;
use App\Modules\Contactos\Infrastructure\Http\Livewire\ListaContactos;
use App\Modules\Contactos\Infrastructure\Persistence\Repositories\AltaContactosEnLoteEloquent;
use App\Modules\Contactos\Infrastructure\Persistence\Repositories\EloquentContactoRepository;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class ContactosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ContactoRepository::class, EloquentContactoRepository::class);
        $this->app->bind(AltaContactosEnLote::class, AltaContactosEnLoteEloquent::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(resource_path('views/modules/contactos'), 'contactos');

        Livewire::component('contactos.lista-contactos', ListaContactos::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ExtraerContactosDeCamposCommand::class]);
        }
    }
}
