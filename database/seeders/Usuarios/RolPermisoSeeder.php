<?php

declare(strict_types=1);

namespace Database\Seeders\Usuarios;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class RolPermisoSeeder extends Seeder
{
    /**
     * Matriz de permisos por rol (excluye ADMIN_GLOBAL — pasa Gate::before sin necesitar matriz).
     *
     * Criterio:
     *   - SUPERVISOR: ve + opera + gestiona todo dentro de su proyecto. NO define campos ni entidades configurables.
     *   - GESTOR: ve + crea gestiones/contactos + edita valores de campos + trabaja su bandeja. NO gestiona usuarios/catálogos/equipos/configuración ni define campos/entidades.
     *   - AUDITOR: solo lectura + export de auditoría + reportes.
     *
     * @var array<string, list<string>>
     */
    private const MATRIZ = [
        'SUPERVISOR' => [
            // Gestiones
            'gestiones.ver', 'gestiones.crear', 'gestiones.editar', 'gestiones.administrar',
            // Compromisos
            'compromisos.ver', 'compromisos.crear', 'compromisos.resolver', 'compromisos.cancelar', 'compromisos.administrar',
            // Personas
            'personas.ver', 'personas.crear', 'personas.editar', 'personas.administrar',
            // Casos
            'casos.ver', 'casos.crear', 'casos.editar', 'casos.cerrar', 'casos.reabrir', 'casos.administrar',
            // Contactos
            'contactos.ver', 'contactos.crear', 'contactos.editar', 'contactos.eliminar',
            // Campañas
            'campanas.ver', 'campanas.crear', 'campanas.editar', 'campanas.gestionar', 'campanas.administrar',
            // Asignaciones
            'asignaciones.ver_propia', 'asignaciones.ver_equipo',
            'asignaciones.crear', 'asignaciones.reasignar', 'asignaciones.cerrar', 'asignaciones.administrar',
            // Usuarios del proyecto
            'usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'usuarios.gestionar', 'usuarios.administrar',
            // Equipos
            'equipos.ver', 'equipos.crear', 'equipos.editar', 'equipos.administrar',
            // Catálogos
            'catalogos.ver', 'catalogos.crear', 'catalogos.editar', 'catalogos.gestionar', 'catalogos.administrar',
            // Reportes
            'reportes.operativos', 'reportes.analiticos', 'reportes.exportar',
            'reportes.constructor.gestionar', 'reportes.constructor.ejecutar', 'reportes.constructor.exportar',
            // Importaciones
            'importaciones.ver', 'importaciones.crear', 'importaciones.procesar',
            // Auditoría
            'auditoria.ver',
            // Notificaciones
            'notificaciones.ver',
            // Campos personalizados — VALORES sí, DEFINICIONES no
            'campos.ver', 'campos.editar',
            // Entidades configurables — VER/CREAR/EDITAR registros; NO DEFINIR
            'entidades.ver', 'entidades.crear', 'entidades.editar', 'entidades.eliminar',
        ],
        'GESTOR' => [
            // Gestiones — crea, ve; no elimina ni administra
            'gestiones.ver', 'gestiones.crear',
            // Compromisos — ve, crea, resuelve; no cancela global ni administra
            'compromisos.ver', 'compromisos.crear', 'compromisos.resolver',
            // Personas — operativo
            'personas.ver', 'personas.crear', 'personas.editar',
            // Casos — ve y edita; no cierra/reabre ni administra
            'casos.ver', 'casos.editar',
            // Contactos
            'contactos.ver', 'contactos.crear', 'contactos.editar',
            // Asignaciones — solo propia
            'asignaciones.ver_propia',
            // Notificaciones propias
            'notificaciones.ver',
            // Campos personalizados — SOLO VALORES (NUNCA definir)
            'campos.ver', 'campos.editar',
            // Entidades configurables — ve y edita registros de lo que el admin le definió. NUNCA define.
            'entidades.ver', 'entidades.crear', 'entidades.editar',
        ],
        'AUDITOR' => [
            // Solo lectura operativa
            'gestiones.ver',
            'compromisos.ver',
            'personas.ver',
            'casos.ver',
            'contactos.ver',
            'campanas.ver',
            'equipos.ver',
            'usuarios.ver',
            'catalogos.ver',
            'asignaciones.ver_equipo',
            'reportes.operativos', 'reportes.analiticos', 'reportes.exportar',
            'reportes.constructor.ejecutar',
            'auditoria.ver', 'auditoria.exportar',
            'notificaciones.ver',
            'campos.ver',
            'entidades.ver',
        ],
    ];

    public function run(): void
    {
        /** @var array<string, int> $rolIds */
        $rolIds = DB::table('roles')->pluck('id', 'codigo')->all();
        /** @var array<string, int> $permisoIds */
        $permisoIds = DB::table('permisos')->pluck('id', 'codigo')->all();

        // ADMIN_GLOBAL: mapeo explícito a todos los permisos para consistencia en la tabla,
        // aunque su Gate::before corta antes de consultar permisos.
        $filas = [];
        $adminGlobalId = $rolIds['ADMIN_GLOBAL'] ?? null;
        if ($adminGlobalId !== null) {
            foreach ($permisoIds as $pid) {
                $filas[] = ['rol_id' => $adminGlobalId, 'permiso_id' => $pid];
            }
        }

        foreach (self::MATRIZ as $rolCodigo => $codigos) {
            $rolId = $rolIds[$rolCodigo] ?? null;
            if ($rolId === null) {
                continue;
            }
            foreach ($codigos as $permisoCodigo) {
                $permisoId = $permisoIds[$permisoCodigo] ?? null;
                if ($permisoId === null) {
                    continue;
                }
                $filas[] = ['rol_id' => $rolId, 'permiso_id' => $permisoId];
            }
        }

        if ($filas === []) {
            return;
        }

        DB::table('rol_permiso')->upsert($filas, ['rol_id', 'permiso_id'], ['rol_id']);
    }
}
