<?php

declare(strict_types=1);

namespace App\Modules\Catalogos\Infrastructure\Providers;

use App\Modules\Catalogos\Application\Listeners\CrearEstadosCasoPorDefecto;
use App\Modules\Tenancy\Domain\Events\ProyectoCreado;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

final class CatalogosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // `ConsultaResultado` lo bindea `GestionesServiceProvider` (adapter v2).
        // El adapter v1 en `App\Modules\Catalogos\Infrastructure\Adapters\ConsultaResultadoEloquent`
        // quedó obsoleto — se archivará. No registrar aquí el binding legacy.
    }

    public function boot(): void
    {
        // Namespace de vistas conservado para futuras pantallas operativas;
        // los Livewires de definición de catálogos fueron absorbidos por el
        // wizard "Configurar proyecto" (F36 P9).
        View::addNamespace('catalogos', resource_path('views/modules/catalogos'));

        // F35-D: sembrar estados ABIERTO + CERRADO al crear cada proyecto.
        Event::listen(ProyectoCreado::class, [CrearEstadosCasoPorDefecto::class, 'handle']);
    }
}
