<?php

declare(strict_types=1);

namespace Database\Seeders\Catalogos;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class PaisesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['codigo_iso2' => 'EC', 'codigo_iso3' => 'ECU', 'nombre' => 'Ecuador',   'activo' => true, 'orden' => 10],
            ['codigo_iso2' => 'CO', 'codigo_iso3' => 'COL', 'nombre' => 'Colombia',  'activo' => true, 'orden' => 20],
            ['codigo_iso2' => 'PE', 'codigo_iso3' => 'PER', 'nombre' => 'Perú',      'activo' => true, 'orden' => 30],
            ['codigo_iso2' => 'MX', 'codigo_iso3' => 'MEX', 'nombre' => 'México',    'activo' => true, 'orden' => 40],
            ['codigo_iso2' => 'CL', 'codigo_iso3' => 'CHL', 'nombre' => 'Chile',     'activo' => true, 'orden' => 50],
            ['codigo_iso2' => 'AR', 'codigo_iso3' => 'ARG', 'nombre' => 'Argentina', 'activo' => true, 'orden' => 60],
            ['codigo_iso2' => 'US', 'codigo_iso3' => 'USA', 'nombre' => 'Estados Unidos', 'activo' => true, 'orden' => 70],
            ['codigo_iso2' => 'ES', 'codigo_iso3' => 'ESP', 'nombre' => 'España',    'activo' => true, 'orden' => 80],
        ];

        DB::table('paises')->upsert($rows, ['codigo_iso2'], ['codigo_iso3', 'nombre', 'activo', 'orden']);
    }
}
