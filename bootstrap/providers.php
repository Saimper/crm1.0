<?php

use App\Modules\Asignaciones\Infrastructure\Providers\AsignacionesServiceProvider;
use App\Modules\Auditoria\Infrastructure\Providers\AuditoriaServiceProvider;
use App\Modules\Campanas\Infrastructure\Providers\CampanasServiceProvider;
use App\Modules\CamposPersonalizados\Infrastructure\Providers\CamposPersonalizadosServiceProvider;
use App\Modules\Casos\Infrastructure\Providers\CasosServiceProvider;
use App\Modules\Catalogos\Infrastructure\Providers\CatalogosServiceProvider;
use App\Modules\Cobranza\Infrastructure\Providers\CobranzaServiceProvider;
use App\Modules\Compromisos\Infrastructure\Providers\CompromisosServiceProvider;
use App\Modules\Contactos\Infrastructure\Providers\ContactosServiceProvider;
use App\Modules\Cx\Infrastructure\Providers\CxServiceProvider;
use App\Modules\EntidadesConfigurables\Infrastructure\Providers\EntidadesConfigurablesServiceProvider;
use App\Modules\Gestiones\Infrastructure\Providers\GestionesServiceProvider;
use App\Modules\Importaciones\Infrastructure\Providers\ImportacionesServiceProvider;
use App\Modules\Integracion\Infrastructure\Providers\IntegracionServiceProvider;
use App\Modules\Notificaciones\Infrastructure\Providers\NotificacionesServiceProvider;
use App\Modules\Personas\Infrastructure\Providers\PersonasServiceProvider;
use App\Modules\Reportes\Infrastructure\Providers\ReportesServiceProvider;
use App\Modules\Servicio\Infrastructure\Providers\ServicioServiceProvider;
use App\Modules\Tenancy\Infrastructure\Providers\TenancyServiceProvider;
use App\Modules\Usuarios\Infrastructure\Providers\UsuariosServiceProvider;
use App\Modules\Venta\Infrastructure\Providers\VentaServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\VoltServiceProvider;

return [
    TenancyServiceProvider::class,
    UsuariosServiceProvider::class,
    CasosServiceProvider::class,
    CompromisosServiceProvider::class,
    PersonasServiceProvider::class,
    ContactosServiceProvider::class,
    GestionesServiceProvider::class,
    CampanasServiceProvider::class,
    AsignacionesServiceProvider::class,
    CamposPersonalizadosServiceProvider::class,
    CobranzaServiceProvider::class,
    CxServiceProvider::class,
    VentaServiceProvider::class,
    ServicioServiceProvider::class,
    ReportesServiceProvider::class,
    ImportacionesServiceProvider::class,
    CatalogosServiceProvider::class,
    AuditoriaServiceProvider::class,
    NotificacionesServiceProvider::class,
    EntidadesConfigurablesServiceProvider::class,
    IntegracionServiceProvider::class,

    AppServiceProvider::class,
    VoltServiceProvider::class,
];
