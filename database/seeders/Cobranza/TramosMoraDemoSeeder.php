<?php

declare(strict_types=1);

namespace Database\Seeders\Cobranza;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class TramosMoraDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $filas = [
            ['codigo' => 'MORA_00',        'nombre' => 'Al día',          'dias_desde' => 0,   'dias_hasta' => 0,    'orden' => 10],
            ['codigo' => 'MORA_01_30',     'nombre' => '1–30 días',       'dias_desde' => 1,   'dias_hasta' => 30,   'orden' => 20],
            ['codigo' => 'MORA_31_60',     'nombre' => '31–60 días',      'dias_desde' => 31,  'dias_hasta' => 60,   'orden' => 30],
            ['codigo' => 'MORA_61_90',     'nombre' => '61–90 días',      'dias_desde' => 61,  'dias_hasta' => 90,   'orden' => 40],
            ['codigo' => 'MORA_91_180',    'nombre' => '91–180 días',     'dias_desde' => 91,  'dias_hasta' => 180,  'orden' => 50],
            ['codigo' => 'MORA_180_PLUS',  'nombre' => 'Más de 180 días', 'dias_desde' => 181, 'dias_hasta' => null, 'orden' => 60],
        ];

        foreach ($filas as $f) {
            $existe = DB::table('tramos_mora')
                ->where('proyecto_id', $proyectoId)
                ->where('codigo', $f['codigo'])
                ->exists();
            if ($existe) {
                continue;
            }

            DB::table('tramos_mora')->insert([
                'proyecto_id' => $proyectoId,
                'codigo'      => $f['codigo'],
                'nombre'      => $f['nombre'],
                'dias_desde'  => $f['dias_desde'],
                'dias_hasta'  => $f['dias_hasta'],
                'activo'      => true,
                'orden'       => $f['orden'],
            ]);
        }
    }
}
