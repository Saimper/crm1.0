<?php

declare(strict_types=1);

namespace Database\Seeders\Usuarios;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['codigo' => 'ADMIN',      'nombre' => 'Administrador',   'descripcion' => 'Acceso total al sistema',                           'activo' => true, 'orden' => 10],
            ['codigo' => 'SUPERVISOR', 'nombre' => 'Supervisor',      'descripcion' => 'Supervisa equipos, reasigna y consulta reportes',   'activo' => true, 'orden' => 20],
            ['codigo' => 'GESTOR',     'nombre' => 'Gestor',          'descripcion' => 'Trabaja su bandeja: registra gestiones y promesas', 'activo' => true, 'orden' => 30],
            ['codigo' => 'AUDITOR',    'nombre' => 'Auditor',         'descripcion' => 'Solo lectura con acceso a auditoría',               'activo' => true, 'orden' => 40],
        ];

        DB::table('roles')->upsert(
            $rows,
            ['codigo'],
            ['nombre', 'descripcion', 'activo', 'orden'],
        );
    }
}
