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
            ['codigo' => 'ADMIN_GLOBAL', 'nombre' => 'Administrador global',  'descripcion' => 'Acceso total al sistema cross-project',          'es_global' => true,  'activo' => true, 'orden' => 0],
            ['codigo' => 'SUPERVISOR',   'nombre' => 'Supervisor',            'descripcion' => 'Supervisa equipos del proyecto y ve reportes',   'es_global' => false, 'activo' => true, 'orden' => 10],
            ['codigo' => 'GESTOR',       'nombre' => 'Gestor',                'descripcion' => 'Trabaja la bandeja y registra gestiones',        'es_global' => false, 'activo' => true, 'orden' => 20],
            ['codigo' => 'AUDITOR',      'nombre' => 'Auditor',               'descripcion' => 'Solo lectura con acceso a auditoría del proyecto', 'es_global' => false, 'activo' => true, 'orden' => 30],
        ];

        DB::table('roles')->upsert(
            $rows,
            ['codigo'],
            ['nombre', 'descripcion', 'es_global', 'activo', 'orden'],
        );
    }
}
