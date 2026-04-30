<?php

use App\Modules\Auditoria\Infrastructure\Http\Controllers\ExportarAuditoriaController;
use App\Modules\Importaciones\Infrastructure\Http\Controllers\ExportarCasosController;
use App\Modules\Importaciones\Infrastructure\Http\Controllers\ExportarCompromisosController;
use App\Modules\Importaciones\Infrastructure\Http\Controllers\ExportarGestionesController;
use App\Modules\Importaciones\Infrastructure\Http\Controllers\ExportarPersonasController;
use App\Modules\Reportes\Infrastructure\Http\Controllers\ExportarReporteController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::prefix('proyectos/{proyecto_id}')
        ->middleware('proyecto.activo')
        ->group(function (): void {
            Route::view('/', 'tenancy::proyecto-dashboard')->name('proyectos.dashboard');

            Route::view('/personas', 'personas::listado-page')
                ->middleware('can:personas.ver')
                ->name('proyectos.personas.lista');

            Route::view('/personas/crear', 'personas::crear-page')
                ->middleware('can:personas.crear')
                ->name('proyectos.personas.crear');

            Route::get('/personas/{persona}/contactos', fn (int $proyecto_id, string $persona) => view('contactos::lista-page', [
                'persona' => $persona,
            ]))->name('proyectos.personas.contactos');

            Route::view('/bandeja', 'asignaciones::bandeja-page')
                ->middleware('can:asignaciones.ver_propia')
                ->name('proyectos.bandeja');

            Route::view('/bandeja/equipo', 'asignaciones::bandeja-equipo-page')
                ->middleware('can:asignaciones.ver_equipo')
                ->name('proyectos.bandeja.equipo');

            Route::view('/asignaciones/masiva', 'asignaciones::asignar-masivamente-page')
                ->middleware('can:asignaciones.reasignar')
                ->name('proyectos.asignaciones.masiva');

            Route::view('/asignaciones/reasignar', 'asignaciones::reasignar-entre-equipos-page')
                ->middleware('can:asignaciones.reasignar')
                ->name('proyectos.asignaciones.reasignar');

            Route::get('/trabajo/{persona}/{caso?}', fn (int $proyecto_id, string $persona, ?string $caso = null) => view('casos::vista-de-trabajo-page', [
                'persona' => $persona,
                'caso' => $caso,
            ]))
                ->middleware('can:casos.ver')
                ->name('proyectos.trabajo');

            Route::view('/reportes/operativos', 'reportes::dashboard-page')
                ->middleware('can:reportes.operativos')
                ->name('proyectos.reportes.operativos');

            Route::view('/reportes/analiticos', 'reportes::dashboard-analitico-page')
                ->middleware('can:reportes.analiticos')
                ->name('proyectos.reportes.analiticos');

            Route::view('/reportes/equipos', 'reportes::reporte-equipos-page')
                ->middleware('can:reportes.operativos')
                ->name('proyectos.reportes.equipos');

            Route::view('/reportes/custom', 'reportes::listado-custom-page')
                ->middleware('can:reportes.constructor.ejecutar')
                ->name('proyectos.reportes.custom');

            Route::view('/reportes/custom/nuevo', 'reportes::constructor-page')
                ->middleware('can:reportes.constructor.gestionar')
                ->name('proyectos.reportes.custom.nuevo');

            Route::get('/reportes/custom/{definicion_id}/editar', fn (int $proyecto_id, int $definicion_id) => view('reportes::constructor-page', [
                'definicionId' => $definicion_id,
            ]))
                ->middleware('can:reportes.constructor.gestionar')
                ->whereNumber('definicion_id')
                ->name('proyectos.reportes.custom.editar');

            Route::get('/reportes/custom/{definicion_id}/exportar',
                ExportarReporteController::class)
                ->middleware('can:reportes.constructor.exportar')
                ->whereNumber('definicion_id')
                ->name('proyectos.reportes.custom.exportar');

            Route::view('/importaciones', 'importaciones::page')
                ->middleware('can:importaciones.crear')
                ->name('proyectos.importaciones');

            Route::get('/importaciones/personas/exportar',
                ExportarPersonasController::class)
                ->middleware('can:importaciones.crear')
                ->name('proyectos.importaciones.exportar-personas');

            Route::get('/importaciones/casos/exportar',
                ExportarCasosController::class)
                ->middleware('can:importaciones.crear')
                ->name('proyectos.importaciones.exportar-casos');

            Route::get('/importaciones/gestiones/exportar',
                ExportarGestionesController::class)
                ->middleware('can:importaciones.crear')
                ->name('proyectos.importaciones.exportar-gestiones');

            Route::get('/importaciones/compromisos/exportar',
                ExportarCompromisosController::class)
                ->middleware('can:importaciones.crear')
                ->name('proyectos.importaciones.exportar-compromisos');

            Route::view('/catalogos', 'catalogos::page')
                ->middleware('can:catalogos.gestionar')
                ->name('proyectos.catalogos');

            Route::view('/carteras', 'tenancy::admin.carteras-proyecto-page')
                ->middleware('can:catalogos.gestionar')
                ->name('proyectos.carteras');

            Route::view('/usuarios', 'usuarios::admin.gestion-usuarios-proyecto-page')
                ->middleware('can:usuarios.gestionar')
                ->name('proyectos.usuarios');

            Route::view('/admin/roles-custom', 'usuarios::admin.roles-custom-page')
                ->middleware('can:roles.gestionar')
                ->name('proyectos.admin.roles-custom');

            Route::view('/admin/matriz-permisos', 'usuarios::admin.matriz-permisos-page')
                ->middleware('can:roles.gestionar')
                ->name('proyectos.admin.matriz-permisos');

            Route::view('/equipos', 'usuarios::admin.equipos-proyecto-page')
                ->middleware('can:usuarios.gestionar')
                ->name('proyectos.equipos');

            Route::view('/auditoria', 'auditoria::page')
                ->middleware('can:auditoria.ver')
                ->name('proyectos.auditoria');

            Route::get('/auditoria/exportar',
                ExportarAuditoriaController::class)
                ->middleware('can:auditoria.ver')
                ->name('proyectos.auditoria.exportar');

            Route::view('/notificaciones', 'notificaciones::page')
                ->middleware('can:notificaciones.ver')
                ->name('proyectos.notificaciones');

            Route::get('/entidades/{entidad_id}',
                function (int $proyecto_id, int $entidad_id) {
                    return view('entidades::operativo.page', [
                        'proyectoId' => $proyecto_id,
                        'entidadId' => (int) $entidad_id,
                    ]);
                }
            )->middleware('can:entidades.ver')->name('proyectos.entidades.registros');
        });

    Route::prefix('admin')
        ->middleware('admin.global')
        ->group(function (): void {
            Route::view('/', 'tenancy::admin-dashboard')->name('admin.dashboard');
            Route::view('/campos-personalizados', 'campos_personalizados::admin.page')
                ->name('admin.campos-personalizados');
            Route::view('/mandantes', 'tenancy::admin.mandantes-page')
                ->name('admin.mandantes');
            Route::view('/proyectos', 'tenancy::admin.proyectos-page')
                ->name('admin.proyectos');
            Route::view('/usuarios', 'usuarios::admin.page')
                ->name('admin.usuarios');
            Route::view('/entidades-configurables', 'entidades::admin.page')
                ->name('admin.entidades-configurables');
        });
});

Route::view('profile', 'profile')->middleware(['auth'])->name('profile');

// Rutas v1 deshabilitadas durante Fase 0 — se reactivarán bajo /proyectos/{proyecto_id}/... en Fase 1.I (bandeja + trabajo) y posteriores.

require __DIR__.'/auth.php';
