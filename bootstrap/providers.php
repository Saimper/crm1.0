<?php

return [
    App\Modules\Tenancy\Infrastructure\Providers\TenancyServiceProvider::class,
    App\Modules\Usuarios\Infrastructure\Providers\UsuariosServiceProvider::class,
    App\Modules\Casos\Infrastructure\Providers\CasosServiceProvider::class,
    App\Modules\Compromisos\Infrastructure\Providers\CompromisosServiceProvider::class,
    App\Modules\Personas\Infrastructure\Providers\PersonasServiceProvider::class,
    App\Modules\Contactos\Infrastructure\Providers\ContactosServiceProvider::class,
    App\Modules\Gestiones\Infrastructure\Providers\GestionesServiceProvider::class,
    App\Modules\Campanas\Infrastructure\Providers\CampanasServiceProvider::class,
    App\Modules\Asignaciones\Infrastructure\Providers\AsignacionesServiceProvider::class,
    App\Modules\CamposPersonalizados\Infrastructure\Providers\CamposPersonalizadosServiceProvider::class,
    App\Modules\Cobranza\Infrastructure\Providers\CobranzaServiceProvider::class,
    App\Modules\Cx\Infrastructure\Providers\CxServiceProvider::class,
    App\Modules\Venta\Infrastructure\Providers\VentaServiceProvider::class,
    App\Modules\Servicio\Infrastructure\Providers\ServicioServiceProvider::class,
    App\Modules\Reportes\Infrastructure\Providers\ReportesServiceProvider::class,
    App\Modules\Importaciones\Infrastructure\Providers\ImportacionesServiceProvider::class,
    App\Modules\Catalogos\Infrastructure\Providers\CatalogosServiceProvider::class,
    App\Modules\Auditoria\Infrastructure\Providers\AuditoriaServiceProvider::class,
    App\Modules\Notificaciones\Infrastructure\Providers\NotificacionesServiceProvider::class,
    App\Modules\EntidadesConfigurables\Infrastructure\Providers\EntidadesConfigurablesServiceProvider::class,
    App\Modules\Integracion\Infrastructure\Providers\IntegracionServiceProvider::class,

    App\Providers\AppServiceProvider::class,
    App\Providers\VoltServiceProvider::class,
];
