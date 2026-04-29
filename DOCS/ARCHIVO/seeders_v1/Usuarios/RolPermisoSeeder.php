<?php

declare(strict_types=1);

namespace Database\Seeders\Usuarios;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class RolPermisoSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    private const MATRIZ = [
        'ADMIN' => ['*'],

        'SUPERVISOR' => [
            'gestiones.ver', 'gestiones.crear',
            'promesas.ver', 'promesas.resolver',
            'clientes.ver', 'clientes.crear', 'clientes.editar',
            'productos.ver', 'productos.editar',
            'contactos.crear', 'contactos.editar',
            'campanas.ver', 'campanas.gestionar',
            'asignaciones.ver_propia', 'asignaciones.ver_equipo', 'asignaciones.reasignar',
            'reportes.operativos', 'reportes.analiticos',
            'catalogos.ver',
            'importaciones.crear', 'importaciones.procesar',
        ],

        'GESTOR' => [
            'gestiones.ver', 'gestiones.crear',
            'promesas.ver', 'promesas.resolver',
            'clientes.ver', 'clientes.crear', 'clientes.editar',
            'productos.ver',
            'contactos.crear', 'contactos.editar',
            'asignaciones.ver_propia',
        ],

        'AUDITOR' => [
            'gestiones.ver',
            'promesas.ver',
            'clientes.ver',
            'productos.ver',
            'campanas.ver',
            'reportes.operativos', 'reportes.analiticos',
            'catalogos.ver',
            'auditoria.ver',
        ],
    ];

    public function run(): void
    {
        /** @var array<string, int> $rolIds */
        $rolIds = DB::table('roles')->pluck('id', 'codigo')->all();
        /** @var array<string, int> $permisoIds */
        $permisoIds = DB::table('permisos')->pluck('id', 'codigo')->all();

        $filas = [];
        foreach (self::MATRIZ as $rolCodigo => $codigos) {
            $rolId = $rolIds[$rolCodigo] ?? null;
            if ($rolId === null) {
                continue;
            }

            $permisosEfectivos = $codigos === ['*'] ? array_keys($permisoIds) : $codigos;

            foreach ($permisosEfectivos as $permisoCodigo) {
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

        DB::table('rol_permiso')->upsert(
            $filas,
            ['rol_id', 'permiso_id'],
            ['rol_id'],
        );
    }
}
