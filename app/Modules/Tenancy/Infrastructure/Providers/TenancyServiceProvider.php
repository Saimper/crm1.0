<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Providers;

use App\Modules\Tenancy\Domain\ConfiguracionProyecto\CalculadorAvanceConfiguracion;
use App\Modules\Tenancy\Domain\Contracts\CarteraRepository;
use App\Modules\Tenancy\Domain\Contracts\MandanteRepository;
use App\Modules\Tenancy\Domain\Contracts\ProyectoRepository;
use App\Modules\Tenancy\Infrastructure\Configuracion\Verificadores\CamposPersonalizadosVerificador;
use App\Modules\Tenancy\Infrastructure\Configuracion\Verificadores\CarterasVerificador;
use App\Modules\Tenancy\Infrastructure\Configuracion\Verificadores\CatalogosTipoVerificador;
use App\Modules\Tenancy\Infrastructure\Configuracion\Verificadores\DatosProyectoVerificador;
use App\Modules\Tenancy\Infrastructure\Configuracion\Verificadores\EstadosCasoVerificador;
use App\Modules\Tenancy\Infrastructure\Configuracion\Verificadores\MotivosNoContactoVerificador;
use App\Modules\Tenancy\Infrastructure\Configuracion\Verificadores\ResultadosVerificador;
use App\Modules\Tenancy\Infrastructure\Configuracion\Verificadores\ResumenVerificador;
use App\Modules\Tenancy\Infrastructure\Configuracion\Verificadores\TiposGestionVerificador;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\AdminMandantes;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\AdminProyectos;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\CatalogosTipo\CatalogoCategoriasTicket;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\CatalogosTipo\CatalogoEstadosTecnicos;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\CatalogosTipo\CatalogoEtapasEmbudo;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\CatalogosTipo\CatalogoNivelesEscalamiento;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\CatalogosTipo\CatalogoNivelesSla;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\CatalogosTipo\CatalogoPrioridadesTicket;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\CatalogosTipo\CatalogoProductosVenta;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\CatalogosTipo\CatalogoTiposAccionServicio;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\CatalogosTipo\CatalogoTiposPago;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\CatalogosTipo\CatalogoTramosMora;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\PasoCamposPersonalizados;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\PasoCarteras;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\PasoCatalogosTipo;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\PasoDatosProyecto;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\PasoEstadosCaso;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\PasoMotivosNoContacto;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\PasoResultados;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\PasoResumen;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\PasoTiposGestion;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorProyecto;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\SelectorProyecto;
use App\Modules\Tenancy\Infrastructure\Http\Middleware\ResolverProyectoActivo;
use App\Modules\Tenancy\Infrastructure\Persistence\Repositories\EloquentCarteraRepository;
use App\Modules\Tenancy\Infrastructure\Persistence\Repositories\EloquentMandanteRepository;
use App\Modules\Tenancy\Infrastructure\Persistence\Repositories\EloquentProyectoRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MandanteRepository::class, EloquentMandanteRepository::class);
        $this->app->bind(ProyectoRepository::class, EloquentProyectoRepository::class);
        $this->app->bind(CarteraRepository::class, EloquentCarteraRepository::class);

        $this->app->bind(CalculadorAvanceConfiguracion::class, function (Application $app): CalculadorAvanceConfiguracion {
            return new CalculadorAvanceConfiguracion([
                $app->make(DatosProyectoVerificador::class),
                $app->make(CarterasVerificador::class),
                $app->make(EstadosCasoVerificador::class),
                $app->make(TiposGestionVerificador::class),
                $app->make(ResultadosVerificador::class),
                $app->make(MotivosNoContactoVerificador::class),
                $app->make(CatalogosTipoVerificador::class),
                $app->make(CamposPersonalizadosVerificador::class),
                $app->make(ResumenVerificador::class),
            ]);
        });
    }

    public function boot(Router $router): void
    {
        $this->loadViewsFrom(resource_path('views/modules/tenancy'), 'tenancy');

        $router->aliasMiddleware('proyecto.activo', ResolverProyectoActivo::class);

        // Persistent middleware: se ejecuta también en /livewire/update para que
        // `tenancy.proyecto_activo` siga bindeado al disparar acciones de Livewire
        // desde una página dentro de /proyectos/{id}/... (extrae el id del Referer).
        Livewire::addPersistentMiddleware([
            ResolverProyectoActivo::class,
        ]);

        Livewire::component('tenancy.selector-proyecto', SelectorProyecto::class);
        Livewire::component('tenancy.admin-mandantes', AdminMandantes::class);
        Livewire::component('tenancy.admin-proyectos', AdminProyectos::class);
        Livewire::component('tenancy.configurador-proyecto', ConfiguradorProyecto::class);
        Livewire::component('tenancy.configurador-pasos.paso-datos-proyecto', PasoDatosProyecto::class);
        Livewire::component('tenancy.configurador-pasos.paso-carteras', PasoCarteras::class);
        Livewire::component('tenancy.configurador-pasos.paso-estados-caso', PasoEstadosCaso::class);
        Livewire::component('tenancy.configurador-pasos.paso-tipos-gestion', PasoTiposGestion::class);
        Livewire::component('tenancy.configurador-pasos.paso-resultados', PasoResultados::class);
        Livewire::component('tenancy.configurador-pasos.paso-motivos-no-contacto', PasoMotivosNoContacto::class);

        Livewire::component('tenancy.configurador-pasos.paso-catalogos-tipo', PasoCatalogosTipo::class);
        Livewire::component('tenancy.configurador-pasos.catalogos-tipo.catalogo-tramos-mora', CatalogoTramosMora::class);
        Livewire::component('tenancy.configurador-pasos.catalogos-tipo.catalogo-tipos-pago', CatalogoTiposPago::class);
        Livewire::component('tenancy.configurador-pasos.catalogos-tipo.catalogo-categorias-ticket', CatalogoCategoriasTicket::class);
        Livewire::component('tenancy.configurador-pasos.catalogos-tipo.catalogo-prioridades-ticket', CatalogoPrioridadesTicket::class);
        Livewire::component('tenancy.configurador-pasos.catalogos-tipo.catalogo-niveles-sla', CatalogoNivelesSla::class);
        Livewire::component('tenancy.configurador-pasos.catalogos-tipo.catalogo-niveles-escalamiento', CatalogoNivelesEscalamiento::class);
        Livewire::component('tenancy.configurador-pasos.catalogos-tipo.catalogo-productos-venta', CatalogoProductosVenta::class);
        Livewire::component('tenancy.configurador-pasos.catalogos-tipo.catalogo-etapas-embudo', CatalogoEtapasEmbudo::class);
        Livewire::component('tenancy.configurador-pasos.catalogos-tipo.catalogo-tipos-accion-servicio', CatalogoTiposAccionServicio::class);
        Livewire::component('tenancy.configurador-pasos.catalogos-tipo.catalogo-estados-tecnicos', CatalogoEstadosTecnicos::class);

        Livewire::component('tenancy.configurador-pasos.paso-campos-personalizados', PasoCamposPersonalizados::class);
        Livewire::component('tenancy.configurador-pasos.paso-resumen', PasoResumen::class);
    }
}
