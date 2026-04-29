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
            ['codigo' => 'CED', 'nombre' => 'Cédula de identidad', 'activo' => true, 'orden' => 10, 'metadata' => null],
            ['codigo' => 'RUC', 'nombre' => 'RUC',                 'activo' => true, 'orden' => 20, 'metadata' => null],
            ['codigo' => 'DNI', 'nombre' => 'DNI',                 'activo' => true, 'orden' => 30, 'metadata' => null],
            ['codigo' => 'PAS', 'nombre' => 'Pasaporte',           'activo' => true, 'orden' => 40, 'metadata' => null],
        ];

        DB::table('tipos_identificacion')->upsert(
            $rows,
            ['codigo'],
            ['nombre', 'activo', 'orden', 'metadata'],
        );
    }
}
