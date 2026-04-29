<?php

declare(strict_types=1);

namespace Database\Seeders\Catalogos;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class MonedasSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['codigo_iso' => 'USD', 'nombre' => 'Dólar estadounidense', 'simbolo' => '$',   'decimales' => 2, 'activo' => true, 'orden' => 10],
            ['codigo_iso' => 'EUR', 'nombre' => 'Euro',                 'simbolo' => '€',   'decimales' => 2, 'activo' => true, 'orden' => 20],
            ['codigo_iso' => 'COP', 'nombre' => 'Peso colombiano',      'simbolo' => 'COL$','decimales' => 0, 'activo' => true, 'orden' => 30],
            ['codigo_iso' => 'PEN', 'nombre' => 'Sol peruano',          'simbolo' => 'S/',  'decimales' => 2, 'activo' => true, 'orden' => 40],
            ['codigo_iso' => 'MXN', 'nombre' => 'Peso mexicano',        'simbolo' => 'Mex$','decimales' => 2, 'activo' => true, 'orden' => 50],
            ['codigo_iso' => 'CLP', 'nombre' => 'Peso chileno',         'simbolo' => 'CLP$','decimales' => 0, 'activo' => true, 'orden' => 60],
            ['codigo_iso' => 'ARS', 'nombre' => 'Peso argentino',       'simbolo' => 'AR$', 'decimales' => 2, 'activo' => true, 'orden' => 70],
        ];

        DB::table('monedas')->upsert($rows, ['codigo_iso'], ['nombre', 'simbolo', 'decimales', 'activo', 'orden']);
    }
}
