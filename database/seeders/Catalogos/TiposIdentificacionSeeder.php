<?php

declare(strict_types=1);

namespace Database\Seeders\Catalogos;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class TiposIdentificacionSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['codigo' => 'CED', 'nombre' => 'Cédula de identidad', 'pais' => 'EC', 'activo' => true, 'orden' => 10, 'metadata' => null],
            ['codigo' => 'RUC', 'nombre' => 'RUC',                 'pais' => 'EC', 'activo' => true, 'orden' => 20, 'metadata' => null],
            ['codigo' => 'DNI', 'nombre' => 'DNI',                 'pais' => null, 'activo' => true, 'orden' => 30, 'metadata' => null],
            ['codigo' => 'NIT', 'nombre' => 'NIT',                 'pais' => null, 'activo' => true, 'orden' => 40, 'metadata' => null],
            ['codigo' => 'PAS', 'nombre' => 'Pasaporte',           'pais' => null, 'activo' => true, 'orden' => 50, 'metadata' => null],
        ];

        DB::table('tipos_identificacion')->upsert(
            $rows,
            ['codigo'],
            ['nombre', 'pais', 'activo', 'orden', 'metadata'],
        );
    }
}
