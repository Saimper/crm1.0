<?php

declare(strict_types=1);

namespace Database\Seeders\Catalogos;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class EstadosBaseSistemaSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['codigo' => 'ACTIVO',     'nombre' => 'Activo',     'descripcion' => 'Entidad operativa y visible.',                   'activo' => true, 'orden' => 10],
            ['codigo' => 'INACTIVO',   'nombre' => 'Inactivo',   'descripcion' => 'Entidad pausada; no opera pero es reactivable.',  'activo' => true, 'orden' => 20],
            ['codigo' => 'BLOQUEADO',  'nombre' => 'Bloqueado',  'descripcion' => 'Entidad vetada por política o auditoría.',        'activo' => true, 'orden' => 30],
            ['codigo' => 'SUSPENDIDO', 'nombre' => 'Suspendido', 'descripcion' => 'Entidad suspendida temporalmente.',               'activo' => true, 'orden' => 40],
        ];

        DB::table('estados_base_sistema')->upsert($rows, ['codigo'], ['nombre', 'descripcion', 'activo', 'orden']);
    }
}
