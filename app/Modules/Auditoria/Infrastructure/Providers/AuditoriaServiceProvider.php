<?php

declare(strict_types=1);

namespace App\Modules\Auditoria\Infrastructure\Providers;

use App\Modules\Asignaciones\Infrastructure\Persistence\Models\AsignacionModel;
use App\Modules\Auditoria\Application\Observers\AuditoriaObserver;
use App\Modules\Auditoria\Infrastructure\Http\Livewire\ListadoAuditoria;
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

    public function register(): void {}

    public function boot(): void
    {
        View::addNamespace('auditoria', resource_path('views/modules/auditoria'));
        Livewire::component('auditoria.listado-auditoria', ListadoAuditoria::class);

        foreach (self::MODELOS_AUDITADOS as $clase) {
            $clase::observe(AuditoriaObserver::class);
        }
    }
}
