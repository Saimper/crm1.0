<?php

declare(strict_types=1);

namespace App\Modules\Clientes\Infrastructure\Providers;

use App\Modules\Clientes\Domain\Contracts\ClienteRepository;
use App\Modules\Clientes\Infrastructure\Http\Livewire\CrearCliente;
use App\Modules\Clientes\Infrastructure\Persistence\Repositories\EloquentClienteRepository;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class ClientesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ClienteRepository::class, EloquentClienteRepository::class);
    }

    public function boot(): void
    {
        View::addNamespace('clientes', resource_path('views/modules/clientes'));
        Livewire::component('clientes.crear-cliente', CrearCliente::class);
    }
}
