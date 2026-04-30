<?php

declare(strict_types=1);

namespace Database\Seeders\Usuarios;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Matriz de permisos del sistema. Estructura: {modulo}.{accion}.
 *
 * Acciones canónicas por módulo:
 *   - ver         → lectura
 *   - crear       → alta
 *   - editar      → modificación
 *   - eliminar    → baja lógica
 *   - administrar → todas las acciones del módulo + operaciones avanzadas
 *
 * Se conservan alias legacy (gestionar, reasignar, resolver, etc.) que ya están cableados
 * en el código de módulos anteriores; no se quitan para no romper retrocompatibilidad.
 */
final class PermisosSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // Gestiones
            ['codigo' => 'gestiones.ver',           'nombre' => 'Ver gestiones',                           'grupo' => 'gestiones',     'activo' => true],
            ['codigo' => 'gestiones.crear',         'nombre' => 'Registrar gestiones',                     'grupo' => 'gestiones',     'activo' => true],
            ['codigo' => 'gestiones.editar',        'nombre' => 'Editar gestiones',                        'grupo' => 'gestiones',     'activo' => true],
            ['codigo' => 'gestiones.eliminar',      'nombre' => 'Eliminar gestiones (admin)',              'grupo' => 'gestiones',     'activo' => true],
            ['codigo' => 'gestiones.administrar',   'nombre' => 'Administrar gestiones',                   'grupo' => 'gestiones',     'activo' => true],

            // Compromisos (reemplaza el grupo "promesas" v1; se generaliza en v2)
            ['codigo' => 'compromisos.ver',         'nombre' => 'Ver compromisos',                         'grupo' => 'compromisos',   'activo' => true],
            ['codigo' => 'compromisos.crear',       'nombre' => 'Crear compromisos',                       'grupo' => 'compromisos',   'activo' => true],
            ['codigo' => 'compromisos.resolver',    'nombre' => 'Resolver compromisos',                    'grupo' => 'compromisos',   'activo' => true],
            ['codigo' => 'compromisos.cancelar',    'nombre' => 'Cancelar compromisos',                    'grupo' => 'compromisos',   'activo' => true],
            ['codigo' => 'compromisos.eliminar',    'nombre' => 'Eliminar compromisos (admin)',            'grupo' => 'compromisos',   'activo' => true],
            ['codigo' => 'compromisos.administrar', 'nombre' => 'Administrar compromisos',                 'grupo' => 'compromisos',   'activo' => true],

            // Personas
            ['codigo' => 'personas.ver',            'nombre' => 'Ver personas',                            'grupo' => 'personas',      'activo' => true],
            ['codigo' => 'personas.crear',          'nombre' => 'Crear personas',                          'grupo' => 'personas',      'activo' => true],
            ['codigo' => 'personas.editar',         'nombre' => 'Editar personas',                         'grupo' => 'personas',      'activo' => true],
            ['codigo' => 'personas.eliminar',       'nombre' => 'Eliminar personas (baja lógica)',         'grupo' => 'personas',      'activo' => true],
            ['codigo' => 'personas.administrar',    'nombre' => 'Administrar personas',                    'grupo' => 'personas',      'activo' => true],

            // Casos
            ['codigo' => 'casos.ver',               'nombre' => 'Ver casos',                               'grupo' => 'casos',         'activo' => true],
            ['codigo' => 'casos.crear',             'nombre' => 'Crear casos',                             'grupo' => 'casos',         'activo' => true],
            ['codigo' => 'casos.editar',            'nombre' => 'Editar casos',                            'grupo' => 'casos',         'activo' => true],
            ['codigo' => 'casos.cerrar',            'nombre' => 'Cerrar casos',                            'grupo' => 'casos',         'activo' => true],
            ['codigo' => 'casos.reabrir',           'nombre' => 'Reabrir casos',                           'grupo' => 'casos',         'activo' => true],
            ['codigo' => 'casos.eliminar',          'nombre' => 'Eliminar casos (baja lógica)',            'grupo' => 'casos',         'activo' => true],
            ['codigo' => 'casos.administrar',       'nombre' => 'Administrar casos',                       'grupo' => 'casos',         'activo' => true],

            // Contactos
            ['codigo' => 'contactos.ver',           'nombre' => 'Ver contactos',                           'grupo' => 'contactos',     'activo' => true],
            ['codigo' => 'contactos.crear',         'nombre' => 'Crear contactos',                         'grupo' => 'contactos',     'activo' => true],
            ['codigo' => 'contactos.editar',        'nombre' => 'Editar contactos',                        'grupo' => 'contactos',     'activo' => true],
            ['codigo' => 'contactos.eliminar',      'nombre' => 'Eliminar contactos',                      'grupo' => 'contactos',     'activo' => true],

            // Campañas
            ['codigo' => 'campanas.ver',            'nombre' => 'Ver campañas',                            'grupo' => 'campanas',      'activo' => true],
            ['codigo' => 'campanas.crear',          'nombre' => 'Crear campañas',                          'grupo' => 'campanas',      'activo' => true],
            ['codigo' => 'campanas.editar',         'nombre' => 'Editar campañas',                         'grupo' => 'campanas',      'activo' => true],
            ['codigo' => 'campanas.eliminar',       'nombre' => 'Eliminar campañas',                       'grupo' => 'campanas',      'activo' => true],
            ['codigo' => 'campanas.gestionar',      'nombre' => 'Gestionar campañas',                      'grupo' => 'campanas',      'activo' => true],
            ['codigo' => 'campanas.administrar',    'nombre' => 'Administrar campañas',                    'grupo' => 'campanas',      'activo' => true],

            // Asignaciones
            ['codigo' => 'asignaciones.ver_propia', 'nombre' => 'Ver bandeja propia',                      'grupo' => 'asignaciones',  'activo' => true],
            ['codigo' => 'asignaciones.ver_equipo', 'nombre' => 'Ver bandejas del equipo',                 'grupo' => 'asignaciones',  'activo' => true],
            ['codigo' => 'asignaciones.crear',      'nombre' => 'Crear asignaciones',                      'grupo' => 'asignaciones',  'activo' => true],
            ['codigo' => 'asignaciones.reasignar',  'nombre' => 'Reasignar casos',                         'grupo' => 'asignaciones',  'activo' => true],
            ['codigo' => 'asignaciones.cerrar',     'nombre' => 'Cerrar asignaciones',                     'grupo' => 'asignaciones',  'activo' => true],
            ['codigo' => 'asignaciones.eliminar',   'nombre' => 'Eliminar asignaciones',                   'grupo' => 'asignaciones',  'activo' => true],
            ['codigo' => 'asignaciones.administrar', 'nombre' => 'Administrar asignaciones',                'grupo' => 'asignaciones',  'activo' => true],

            // Usuarios del proyecto
            ['codigo' => 'usuarios.ver',            'nombre' => 'Ver usuarios del proyecto',               'grupo' => 'usuarios',      'activo' => true],
            ['codigo' => 'usuarios.crear',          'nombre' => 'Invitar usuarios al proyecto',            'grupo' => 'usuarios',      'activo' => true],
            ['codigo' => 'usuarios.editar',         'nombre' => 'Editar usuarios del proyecto',            'grupo' => 'usuarios',      'activo' => true],
            ['codigo' => 'usuarios.eliminar',       'nombre' => 'Remover usuarios del proyecto',           'grupo' => 'usuarios',      'activo' => true],
            ['codigo' => 'usuarios.gestionar',      'nombre' => 'Gestionar usuarios del proyecto',         'grupo' => 'usuarios',      'activo' => true],
            ['codigo' => 'usuarios.administrar',    'nombre' => 'Administrar usuarios del proyecto',       'grupo' => 'usuarios',      'activo' => true],

            // Equipos
            ['codigo' => 'equipos.ver',             'nombre' => 'Ver equipos',                             'grupo' => 'equipos',       'activo' => true],
            ['codigo' => 'equipos.crear',           'nombre' => 'Crear equipos',                           'grupo' => 'equipos',       'activo' => true],
            ['codigo' => 'equipos.editar',          'nombre' => 'Editar equipos',                          'grupo' => 'equipos',       'activo' => true],
            ['codigo' => 'equipos.eliminar',        'nombre' => 'Eliminar equipos',                        'grupo' => 'equipos',       'activo' => true],
            ['codigo' => 'equipos.administrar',     'nombre' => 'Administrar equipos',                     'grupo' => 'equipos',       'activo' => true],

            // Catálogos del proyecto
            ['codigo' => 'catalogos.ver',           'nombre' => 'Ver catálogos del proyecto',              'grupo' => 'catalogos',     'activo' => true],
            ['codigo' => 'catalogos.crear',         'nombre' => 'Crear catálogos',                         'grupo' => 'catalogos',     'activo' => true],
            ['codigo' => 'catalogos.editar',        'nombre' => 'Editar catálogos',                        'grupo' => 'catalogos',     'activo' => true],
            ['codigo' => 'catalogos.eliminar',      'nombre' => 'Eliminar catálogos',                      'grupo' => 'catalogos',     'activo' => true],
            ['codigo' => 'catalogos.gestionar',     'nombre' => 'Gestionar catálogos del proyecto',        'grupo' => 'catalogos',     'activo' => true],
            ['codigo' => 'catalogos.administrar',   'nombre' => 'Administrar catálogos',                   'grupo' => 'catalogos',     'activo' => true],

            // Reportes
            ['codigo' => 'reportes.operativos',              'nombre' => 'Ver reportes operativos',                          'grupo' => 'reportes',      'activo' => true],
            ['codigo' => 'reportes.analiticos',              'nombre' => 'Ver reportes analíticos',                          'grupo' => 'reportes',      'activo' => true],
            ['codigo' => 'reportes.exportar',                'nombre' => 'Exportar reportes',                                'grupo' => 'reportes',      'activo' => true],
            ['codigo' => 'reportes.constructor.gestionar',   'nombre' => 'Crear/editar definiciones de reportes custom',     'grupo' => 'reportes',      'activo' => true],
            ['codigo' => 'reportes.constructor.ejecutar',    'nombre' => 'Ejecutar y previsualizar reportes custom',         'grupo' => 'reportes',      'activo' => true],
            ['codigo' => 'reportes.constructor.exportar',    'nombre' => 'Exportar reportes custom (CSV/XLSX)',              'grupo' => 'reportes',      'activo' => true],

            // Importaciones
            ['codigo' => 'importaciones.ver',       'nombre' => 'Ver importaciones',                       'grupo' => 'importaciones', 'activo' => true],
            ['codigo' => 'importaciones.crear',     'nombre' => 'Cargar importaciones',                    'grupo' => 'importaciones', 'activo' => true],
            ['codigo' => 'importaciones.procesar',  'nombre' => 'Procesar importaciones',                  'grupo' => 'importaciones', 'activo' => true],
            ['codigo' => 'importaciones.eliminar',  'nombre' => 'Eliminar importaciones',                  'grupo' => 'importaciones', 'activo' => true],

            // Auditoría
            ['codigo' => 'auditoria.ver',           'nombre' => 'Consultar auditoría',                     'grupo' => 'auditoria',     'activo' => true],
            ['codigo' => 'auditoria.exportar',      'nombre' => 'Exportar auditoría',                      'grupo' => 'auditoria',     'activo' => true],

            // Notificaciones
            ['codigo' => 'notificaciones.ver',      'nombre' => 'Ver notificaciones propias',              'grupo' => 'notificaciones', 'activo' => true],

            // Campos personalizados (solo VALORES para operativos; DEFINICIONES solo admin)
            ['codigo' => 'campos.ver',              'nombre' => 'Ver campos personalizados',               'grupo' => 'campos',        'activo' => true],
            ['codigo' => 'campos.editar',           'nombre' => 'Editar valores de campos personalizados', 'grupo' => 'campos',        'activo' => true],
            ['codigo' => 'campos.definir',          'nombre' => 'Definir campos personalizados (admin)',   'grupo' => 'campos',        'activo' => true],

            // Entidades configurables (Fase 24) — tablas definibles por proyecto/cartera
            ['codigo' => 'entidades.ver',           'nombre' => 'Ver registros de entidades configurables', 'grupo' => 'entidades',     'activo' => true],
            ['codigo' => 'entidades.crear',         'nombre' => 'Crear registros de entidades',            'grupo' => 'entidades',     'activo' => true],
            ['codigo' => 'entidades.editar',        'nombre' => 'Editar registros de entidades',           'grupo' => 'entidades',     'activo' => true],
            ['codigo' => 'entidades.eliminar',      'nombre' => 'Eliminar registros de entidades',         'grupo' => 'entidades',     'activo' => true],
            ['codigo' => 'entidades.definir',       'nombre' => 'Definir entidades configurables (admin)', 'grupo' => 'entidades',     'activo' => true],

            // Configuración del proyecto
            ['codigo' => 'configuracion.ver',       'nombre' => 'Ver configuración',                       'grupo' => 'configuracion', 'activo' => true],
            ['codigo' => 'configuracion.editar',    'nombre' => 'Editar configuración',                    'grupo' => 'configuracion', 'activo' => true],

            // Roles custom (Fase 33) — exclusivo ADMIN_GLOBAL via Gate::before; no se asigna a roles base.
            ['codigo' => 'roles.gestionar',         'nombre' => 'Gestionar roles custom del proyecto',     'grupo' => 'roles',         'activo' => true],
        ];

        DB::table('permisos')->upsert(
            $rows,
            ['codigo'],
            ['nombre', 'grupo', 'activo'],
        );
    }
}
