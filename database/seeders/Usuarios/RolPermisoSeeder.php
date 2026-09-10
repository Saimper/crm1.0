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
     * Exportaciones (ola 04): SUPERVISOR y ADMIN_MANDANTE se llevan las cuatro.
     * AUDITOR sólo gestiones y compromisos: audita actividad, no se lleva el
     * padrón (personas) ni la cartera (casos con saldos y campos del cliente).
     * Ojo, no es «sin datos personales»: el CSV de gestiones lleva identificación,
     * nombre y notas de cada persona gestionada, igual que la auditoría que ya
     * podía exportar. Es una decisión de política, coherente con F32 (ejecuta
     * reportes pero no exporta los custom). Si un cliente quiere que su auditor
     * extraiga cartera, se lo da con un rol custom (F33). GESTOR ninguna.
     *
     * @var array<string, list<string>>
     */
    private const MATRIZ = [
        // F38: ADMIN_MANDANTE administra todos los proyectos de su mandante.
        // Tiene permisos de SUPERVISOR (operativos) + creación/configuración de
        // proyectos dentro del mandante. Su scope (mandante, no proyecto) lo
        // determina la tabla pivote usuario_mandante_rol y la evaluación en
        // User::tienePermiso (ruta mandante).
        'ADMIN_MANDANTE' => [
            'carteras.ver', 'carteras.crear', 'carteras.editar', 'carteras.eliminar',
            // Bandera del rol
            'mandante.administrar',
            // Proyectos del mandante
            'proyectos.crear', 'proyectos.configurar',
            // Gestiones
            'gestiones.ver', 'gestiones.crear', 'gestiones.editar', 'gestiones.administrar', 'gestiones.exportar',
            // Compromisos
            'compromisos.ver', 'compromisos.crear', 'compromisos.resolver', 'compromisos.cancelar', 'compromisos.administrar', 'compromisos.exportar',
            // Personas
            'personas.ver', 'personas.crear', 'personas.editar', 'personas.administrar', 'personas.exportar',
            // Casos
            'casos.ver', 'casos.crear', 'casos.editar', 'casos.cerrar', 'casos.reabrir', 'casos.administrar', 'casos.exportar',
            // Contactos
            'contactos.ver', 'contactos.crear', 'contactos.editar', 'contactos.eliminar',
            // Asignaciones
            'asignaciones.ver_propia', 'asignaciones.ver_equipo',
            'asignaciones.crear', 'asignaciones.reasignar', 'asignaciones.cerrar', 'asignaciones.administrar',
            'asignaciones.autoasignarse',
            // Usuarios — admin_mandante asigna usuarios a sus proyectos
            'usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'usuarios.gestionar', 'usuarios.administrar',
            // Equipos
            'equipos.ver', 'equipos.crear', 'equipos.editar', 'equipos.administrar',
            // Catálogos
            'catalogos.ver', 'catalogos.crear', 'catalogos.editar', 'catalogos.gestionar', 'catalogos.administrar',
            // Reportes
            'reportes.operativos', 'reportes.analiticos',
            'reportes.constructor.gestionar', 'reportes.constructor.ejecutar', 'reportes.constructor.exportar',
            // Importaciones
            'importaciones.ver', 'importaciones.crear', 'importaciones.procesar',
            // Auditoría
            'auditoria.ver', 'auditoria.exportar',
            // Notificaciones
            'notificaciones.ver',
            // Campos personalizados — VALORES sí. DEFINICIONES NO (sigue exclusivo ADMIN_GLOBAL via §13.16/§7.7).
            'campos.ver', 'campos.editar',
            // Entidades configurables — registros sí. DEFINIR no.
            'entidades.ver', 'entidades.crear', 'entidades.editar', 'entidades.eliminar',
        ],
        'SUPERVISOR' => [
            'carteras.ver',
            // Gestiones
            'gestiones.ver', 'gestiones.crear', 'gestiones.editar', 'gestiones.administrar', 'gestiones.exportar',
            // Compromisos
            'compromisos.ver', 'compromisos.crear', 'compromisos.resolver', 'compromisos.cancelar', 'compromisos.administrar', 'compromisos.exportar',
            // Personas
            'personas.ver', 'personas.crear', 'personas.editar', 'personas.administrar', 'personas.exportar',
            // Casos
            'casos.ver', 'casos.crear', 'casos.editar', 'casos.cerrar', 'casos.reabrir', 'casos.administrar', 'casos.exportar',
            // Contactos
            'contactos.ver', 'contactos.crear', 'contactos.editar', 'contactos.eliminar',
            // Asignaciones
            'asignaciones.ver_propia', 'asignaciones.ver_equipo',
            'asignaciones.crear', 'asignaciones.reasignar', 'asignaciones.cerrar', 'asignaciones.administrar',
            'asignaciones.autoasignarse',
            // Usuarios del proyecto
            'usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'usuarios.gestionar', 'usuarios.administrar',
            // Equipos
            'equipos.ver', 'equipos.crear', 'equipos.editar', 'equipos.administrar',
            // Catálogos
            'catalogos.ver', 'catalogos.crear', 'catalogos.editar', 'catalogos.gestionar', 'catalogos.administrar',
            // Reportes
            'reportes.operativos', 'reportes.analiticos',
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
            // Asignaciones — solo propia, y tomar las que no son de nadie
            'asignaciones.ver_propia', 'asignaciones.autoasignarse',
            // Notificaciones propias
            'notificaciones.ver',
            // Campos personalizados — SOLO VALORES (NUNCA definir)
            'campos.ver', 'campos.editar',
            // Entidades configurables — ve y edita registros de lo que el admin le definió. NUNCA define.
            'entidades.ver', 'entidades.crear', 'entidades.editar',
        ],
        'AUDITOR' => [
            // Solo lectura operativa. Exporta actividad (gestiones, compromisos,
            // con identificación y notas), nunca el padrón ni la cartera.
            'gestiones.ver', 'gestiones.exportar',
            'compromisos.ver', 'compromisos.exportar',
            'personas.ver',
            'casos.ver',
            'contactos.ver',
            'equipos.ver',
            'usuarios.ver',
            'catalogos.ver',
            'asignaciones.ver_equipo',
            'reportes.operativos', 'reportes.analiticos',
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
