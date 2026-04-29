<?php

declare(strict_types=1);

namespace Database\Seeders\Catalogos;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class CarterasSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['codigo' => 'CONSUMO',      'nombre' => 'Consumo',      'activo' => true, 'orden' => 10, 'metadata' => null],
            ['codigo' => 'MICROEMPRESA', 'nombre' => 'Microempresa', 'activo' => true, 'orden' => 20, 'metadata' => null],
            ['codigo' => 'VEHICULAR',    'nombre' => 'Vehicular',    'activo' => true, 'orden' => 30, 'metadata' => null],
            ['codigo' => 'HIPOTECARIO',  'nombre' => 'Hipotecario',  'activo' => true, 'orden' => 40, 'metadata' => null],
            ['codigo' => 'COMERCIAL',    'nombre' => 'Comercial',    'activo' => true, 'orden' => 50, 'metadata' => null],
        ];

        DB::table('carteras')->upsert(
            $rows,
            ['codigo'],
            ['nombre', 'activo', 'orden', 'metadata'],
        );
    }
}
