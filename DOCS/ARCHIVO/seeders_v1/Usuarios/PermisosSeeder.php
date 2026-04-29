<?php

declare(strict_types=1);

namespace Database\Seeders\Usuarios;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class PermisosSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // Gestiones
            ['codigo' => 'gestiones.ver',        'nombre' => 'Ver gestiones',               'grupo' => 'gestiones',    'activo' => true],
            ['codigo' => 'gestiones.crear',      'nombre' => 'Registrar gestiones',         'grupo' => 'gestiones',    'activo' => true],

            // Promesas
            ['codigo' => 'promesas.ver',         'nombre' => 'Ver promesas',                'grupo' => 'promesas',     'activo' => true],
            ['codigo' => 'promesas.resolver',    'nombre' => 'Resolver promesas (cumplir/romper/cancelar)', 'grupo' => 'promesas', 'activo' => true],

            // Clientes
            ['codigo' => 'clientes.ver',         'nombre' => 'Ver clientes',                'grupo' => 'clientes',     'activo' => true],
            ['codigo' => 'clientes.crear',       'nombre' => 'Crear clientes',              'grupo' => 'clientes',     'activo' => true],
            ['codigo' => 'clientes.editar',      'nombre' => 'Editar clientes',             'grupo' => 'clientes',     'activo' => true],

            // Productos
            ['codigo' => 'productos.ver',        'nombre' => 'Ver productos',               'grupo' => 'productos',    'activo' => true],
            ['codigo' => 'productos.editar',     'nombre' => 'Editar productos',            'grupo' => 'productos',    'activo' => true],

            // Contactos
            ['codigo' => 'contactos.crear',      'nombre' => 'Crear contactos',             'grupo' => 'contactos',    'activo' => true],
            ['codigo' => 'contactos.editar',     'nombre' => 'Editar contactos',            'grupo' => 'contactos',    'activo' => true],

            // Campañas
            ['codigo' => 'campanas.ver',         'nombre' => 'Ver campañas',                'grupo' => 'campanas',     'activo' => true],
            ['codigo' => 'campanas.gestionar',   'nombre' => 'Gestionar campañas',          'grupo' => 'campanas',     'activo' => true],

            // Asignaciones
            ['codigo' => 'asignaciones.ver_propia', 'nombre' => 'Ver bandeja propia',       'grupo' => 'asignaciones', 'activo' => true],
            ['codigo' => 'asignaciones.ver_equipo', 'nombre' => 'Ver bandejas del equipo',  'grupo' => 'asignaciones', 'activo' => true],
            ['codigo' => 'asignaciones.reasignar',  'nombre' => 'Reasignar productos',      'grupo' => 'asignaciones', 'activo' => true],

            // Usuarios
            ['codigo' => 'usuarios.ver',         'nombre' => 'Ver usuarios',                'grupo' => 'usuarios',     'activo' => true],
            ['codigo' => 'usuarios.gestionar',   'nombre' => 'Gestionar usuarios',          'grupo' => 'usuarios',     'activo' => true],

            // Catálogos
            ['codigo' => 'catalogos.ver',        'nombre' => 'Ver catálogos',               'grupo' => 'catalogos',    'activo' => true],
            ['codigo' => 'catalogos.gestionar',  'nombre' => 'Gestionar catálogos',         'grupo' => 'catalogos',    'activo' => true],

            // Reportes
            ['codigo' => 'reportes.operativos',  'nombre' => 'Ver reportes operativos',     'grupo' => 'reportes',     'activo' => true],
            ['codigo' => 'reportes.analiticos',  'nombre' => 'Ver reportes analíticos',     'grupo' => 'reportes',     'activo' => true],

            // Importaciones
            ['codigo' => 'importaciones.crear',    'nombre' => 'Cargar importaciones',      'grupo' => 'importaciones','activo' => true],
            ['codigo' => 'importaciones.procesar', 'nombre' => 'Procesar importaciones',    'grupo' => 'importaciones','activo' => true],

            // Auditoría
            ['codigo' => 'auditoria.ver',        'nombre' => 'Consultar auditoría',         'grupo' => 'auditoria',    'activo' => true],

            // Configuración
            ['codigo' => 'configuracion.ver',    'nombre' => 'Ver configuración',           'grupo' => 'configuracion','activo' => true],
            ['codigo' => 'configuracion.editar', 'nombre' => 'Editar configuración',        'grupo' => 'configuracion','activo' => true],
        ];

        DB::table('permisos')->upsert(
            $rows,
            ['codigo'],
            ['nombre', 'grupo', 'activo'],
        );
    }
}
