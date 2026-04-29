<?php

declare(strict_types=1);

namespace App\Modules\Contactos\Infrastructure\Providers;

use App\Modules\Contactos\Domain\Contracts\ContactoRepository;
use App\Modules\Contactos\Infrastructure\Http\Livewire\ListaContactos;
use App\Modules\Contactos\Infrastructure\Persistence\Repositories\EloquentContactoRepository;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class ContactosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ContactoRepository::class, EloquentContactoRepository::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(resource_path('views/modules/contactos'), 'contactos');

        Livewire::component('contactos.lista-contactos', ListaContactos::class);
    }
}
