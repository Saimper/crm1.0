<?php

declare(strict_types=1);

namespace App\Modules\Auditoria\Infrastructure\Providers;

use App\Modules\Asignaciones\Infrastructure\Persistence\Models\AsignacionModel;
use App\Modules\Auditoria\Application\Observers\AuditoriaObserver;
use App\Modules\Auditoria\Domain\Contracts\RegistroDeAccionesAdministrativas;
use App\Modules\Auditoria\Domain\Contracts\RegistroDeExportaciones;
use App\Modules\Auditoria\Infrastructure\Http\Livewire\ListadoAuditoria;
use App\Modules\Auditoria\Infrastructure\Persistence\Repositories\RegistroDeAccionesAdministrativasEloquent;
use App\Modules\Auditoria\Infrastructure\Persistence\Repositories\RegistroDeExportacionesEloquent;
use App\Modules\Casos\Infrastructure\Persistence\Models\CasoModel;
use App\Modules\Cobranza\Infrastructure\Persistence\Models\CasoCobranzaModel;
use App\Modules\Cobranza\Infrastructure\Persistence\Models\CompromisoPromesaPagoModel;
use App\Modules\Compromisos\Infrastructure\Persistence\Models\CompromisoModel;
use App\Modules\Cx\Infrastructure\Persistence\Models\CasoTicketCxModel;
use App\Modules\Cx\Infrastructure\Persistence\Models\CompromisoResolucionTicketModel;
use App\Modules\Gestiones\Infrastructure\Persistence\Models\GestionModel;
use App\Modules\Personas\Infrastructure\Persistence\Models\PersonaModel;
use App\Modules\Servicio\Infrastructure\Persistence\Models\CasoServicioModel;
use App\Modules\Servicio\Infrastructure\Persistence\Models\CompromisoAccionServicioModel;
use App\Modules\Venta\Infrastructure\Persistence\Models\CasoLeadVentaModel;
use App\Modules\Venta\Infrastructure\Persistence\Models\CompromisoCierreVentaModel;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class AuditoriaServiceProvider extends ServiceProvider
{
    /**
     * Los modelos cuyo rastro escribe el observer genérico.
     *
     * `User` NO está aquí, y no es un olvido. El observer fotografía todos los
     * atributos del modelo y descarta después los sensibles por lista negra
     * (`AuditoriaObserver::CAMPOS_OMITIDOS`); en la tabla donde viven las
     * credenciales de todo el mundo, esa lista es una que hay que acordarse de
     * ampliar el día que se añada una columna, y el precio de olvidarla es el
     * hash de una contraseña copiado en un registro que además es inmutable.
     * Las cuentas y sus accesos se registran enumerando lo que se guarda, vía
     * `RegistroDeAccionesAdministrativas` — que además llega donde el observer
     * no puede llegar: las pivotes de rol, que no tienen modelo.
     */
    private const MODELOS_AUDITADOS = [
        GestionModel::class,
        CompromisoModel::class,
        PersonaModel::class,
        CasoModel::class,
        CasoCobranzaModel::class,
        CompromisoPromesaPagoModel::class,
        CasoTicketCxModel::class,
        CompromisoResolucionTicketModel::class,
        CasoLeadVentaModel::class,
        CompromisoCierreVentaModel::class,
        CasoServicioModel::class,
        CompromisoAccionServicioModel::class,
        AsignacionModel::class,
    ];

    public function register(): void
    {
        $this->app->bind(RegistroDeExportaciones::class, RegistroDeExportacionesEloquent::class);
        $this->app->bind(
            RegistroDeAccionesAdministrativas::class,
            RegistroDeAccionesAdministrativasEloquent::class,
        );
    }

    public function boot(): void
    {
        View::addNamespace('auditoria', resource_path('views/modules/auditoria'));
        Livewire::component('auditoria.listado-auditoria', ListadoAuditoria::class);

        foreach (self::MODELOS_AUDITADOS as $clase) {
            $clase::observe(AuditoriaObserver::class);
        }

        // El memo proyecto→mandante del observer es estático: vive lo que el
        // proceso, y aquí se le da la vida que de verdad tiene, la de una
        // petición. Al arrancar (una aplicación nueva en el mismo proceso: la
        // suite, un reload de Octane) y en cada rebind de `request`.
        AuditoriaObserver::olvidarMandantes();
        $this->app->rebinding('request', static function (): void {
            AuditoriaObserver::olvidarMandantes();
        });
    }
}
