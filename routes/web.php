<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::prefix('proyectos/{proyecto_id}')
        ->middleware('proyecto.activo')
        ->group(function (): void {
            Route::view('/', 'tenancy::proyecto-dashboard')->name('proyectos.dashboard');

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
                'caso'    => $caso,
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

            Route::view('/importaciones', 'importaciones::page')
                ->middleware('can:importaciones.crear')
                ->name('proyectos.importaciones');

            Route::get('/importaciones/personas/exportar',
                \App\Modules\Importaciones\Infrastructure\Http\Controllers\ExportarPersonasController::class)
                ->middleware('can:importaciones.crear')
                ->name('proyectos.importaciones.exportar-personas');

            Route::get('/importaciones/casos/exportar',
                \App\Modules\Importaciones\Infrastructure\Http\Controllers\ExportarCasosController::class)
                ->middleware('can:importaciones.crear')
                ->name('proyectos.importaciones.exportar-casos');

            Route::get('/importaciones/gestiones/exportar',
                \App\Modules\Importaciones\Infrastructure\Http\Controllers\ExportarGestionesController::class)
                ->middleware('can:importaciones.crear')
                ->name('proyectos.importaciones.exportar-gestiones');

            Route::get('/importaciones/compromisos/exportar',
                \App\Modules\Importaciones\Infrastructure\Http\Controllers\ExportarCompromisosController::class)
                ->middleware('can:importaciones.crear')
                ->name('proyectos.importaciones.exportar-compromisos');

            Route::view('/catalogos', 'catalogos::page')
                ->middleware('can:catalogos.gestionar')
                ->name('proyectos.catalogos');

            Route::view('/usuarios', 'usuarios::admin.gestion-usuarios-proyecto-page')
                ->middleware('can:usuarios.gestionar')
                ->name('proyectos.usuarios');

            Route::view('/equipos', 'usuarios::admin.equipos-proyecto-page')
                ->middleware('can:usuarios.gestionar')
                ->name('proyectos.equipos');

            Route::view('/auditoria', 'auditoria::page')
                ->middleware('can:auditoria.ver')
                ->name('proyectos.auditoria');

            Route::get('/auditoria/exportar',
                \App\Modules\Auditoria\Infrastructure\Http\Controllers\ExportarAuditoriaController::class)
                ->middleware('can:auditoria.ver')
                ->name('proyectos.auditoria.exportar');

            Route::view('/notificaciones', 'notificaciones::page')
                ->middleware('can:compromisos.ver')
                ->name('proyectos.notificaciones');

            Route::get('/entidades/{entidad_id}',
                function (int $proyecto_id, int $entidad_id) {
                    return view('entidades::operativo.page', [
                        'proyectoId' => $proyecto_id,
                        'entidadId'  => (int) $entidad_id,
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
