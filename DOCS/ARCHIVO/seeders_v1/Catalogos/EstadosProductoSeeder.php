<?php

declare(strict_types=1);

namespace Database\Seeders\Catalogos;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class EstadosProductoSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['codigo' => 'VIGENTE',      'nombre' => 'Vigente',              'activo' => true, 'orden' => 10, 'metadata' => null],
            ['codigo' => 'MORA',         'nombre' => 'En mora',              'activo' => true, 'orden' => 20, 'metadata' => null],
            ['codigo' => 'JUDICIAL',     'nombre' => 'En proceso judicial',  'activo' => true, 'orden' => 30, 'metadata' => null],
            ['codigo' => 'REFINANCIADO', 'nombre' => 'Refinanciado',         'activo' => true, 'orden' => 40, 'metadata' => null],
            ['codigo' => 'CASTIGADO',    'nombre' => 'Castigado',            'activo' => true, 'orden' => 50, 'metadata' => null],
            ['codigo' => 'CANCELADO',    'nombre' => 'Cancelado',            'activo' => true, 'orden' => 60, 'metadata' => null],
        ];

        DB::table('estados_producto')->upsert(
            $rows,
            ['codigo'],
            ['nombre', 'activo', 'orden', 'metadata'],
        );
    }
}
