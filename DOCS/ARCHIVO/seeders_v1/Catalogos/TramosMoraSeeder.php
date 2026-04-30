<?php

declare(strict_types=1);

namespace Database\Seeders\Catalogos;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class TramosMoraSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'codigo' => 'AL_DIA',
                'nombre' => 'Al día',
                'activo' => true,
                'orden' => 10,
                'metadata' => json_encode(['dias_desde' => 0,   'dias_hasta' => 0]),
            ],
            [
                'codigo' => 'TRAMO_1_30',
                'nombre' => '1 a 30 días',
                'activo' => true,
                'orden' => 20,
                'metadata' => json_encode(['dias_desde' => 1,   'dias_hasta' => 30]),
            ],
            [
                'codigo' => 'TRAMO_31_60',
                'nombre' => '31 a 60 días',
                'activo' => true,
                'orden' => 30,
                'metadata' => json_encode(['dias_desde' => 31,  'dias_hasta' => 60]),
            ],
            [
                'codigo' => 'TRAMO_61_90',
                'nombre' => '61 a 90 días',
                'activo' => true,
                'orden' => 40,
                'metadata' => json_encode(['dias_desde' => 61,  'dias_hasta' => 90]),
            ],
            [
                'codigo' => 'TRAMO_91_180',
                'nombre' => '91 a 180 días',
                'activo' => true,
                'orden' => 50,
                'metadata' => json_encode(['dias_desde' => 91,  'dias_hasta' => 180]),
            ],
            [
                'codigo' => 'TRAMO_181_360',
                'nombre' => '181 a 360 días',
                'activo' => true,
                'orden' => 60,
                'metadata' => json_encode(['dias_desde' => 181, 'dias_hasta' => 360]),
            ],
            [
                'codigo' => 'MAS_360',
                'nombre' => 'Más de 360 días',
                'activo' => true,
                'orden' => 70,
                'metadata' => json_encode(['dias_desde' => 361, 'dias_hasta' => null]),
            ],
        ];

        DB::table('tramos_mora')->upsert(
            $rows,
            ['codigo'],
            ['nombre', 'activo', 'orden', 'metadata'],
        );
    }
}
