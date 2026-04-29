<?php

declare(strict_types=1);

namespace Database\Seeders\Catalogos;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class CausasMoraSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['codigo' => 'DESEMPLEO',          'nombre' => 'Desempleo',                 'activo' => true, 'orden' => 10,  'metadata' => null],
            ['codigo' => 'REDUCCION_INGRESOS', 'nombre' => 'Reducción de ingresos',     'activo' => true, 'orden' => 20,  'metadata' => null],
            ['codigo' => 'ENFERMEDAD',         'nombre' => 'Enfermedad',                'activo' => true, 'orden' => 30,  'metadata' => null],
            ['codigo' => 'GASTOS_IMPREVISTOS', 'nombre' => 'Gastos imprevistos',        'activo' => true, 'orden' => 40,  'metadata' => null],
            ['codigo' => 'NEGOCIO_CERRADO',    'nombre' => 'Negocio cerrado',           'activo' => true, 'orden' => 50,  'metadata' => null],
            ['codigo' => 'SOBREENDEUDAMIENTO', 'nombre' => 'Sobreendeudamiento',        'activo' => true, 'orden' => 60,  'metadata' => null],
            ['codigo' => 'VIAJE',              'nombre' => 'Viaje o ausencia temporal', 'activo' => true, 'orden' => 70,  'metadata' => null],
            ['codigo' => 'OLVIDO',             'nombre' => 'Olvido',                    'activo' => true, 'orden' => 80,  'metadata' => null],
            ['codigo' => 'DESACUERDO',         'nombre' => 'Desacuerdo con la deuda',   'activo' => true, 'orden' => 90,  'metadata' => null],
            ['codigo' => 'OTRO',               'nombre' => 'Otro',                      'activo' => true, 'orden' => 999, 'metadata' => null],
        ];

        DB::table('causas_mora')->upsert(
            $rows,
            ['codigo'],
            ['nombre', 'activo', 'orden', 'metadata'],
        );
    }
}
