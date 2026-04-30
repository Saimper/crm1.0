<?php

declare(strict_types=1);

namespace Database\Seeders\Cx;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class NivelesSlaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $filas = [
            ['codigo' => 'SLA_4H',  'nombre' => '4 horas',   'horas' => 4,   'orden' => 10],
            ['codigo' => 'SLA_24H', 'nombre' => '24 horas',  'horas' => 24,  'orden' => 20],
            ['codigo' => 'SLA_48H', 'nombre' => '48 horas',  'horas' => 48,  'orden' => 30],
            ['codigo' => 'SLA_72H', 'nombre' => '72 horas',  'horas' => 72,  'orden' => 40],
            ['codigo' => 'SLA_7D',  'nombre' => '7 días',    'horas' => 168, 'orden' => 50],
        ];

        foreach ($filas as $f) {
            $existe = DB::table('niveles_sla')
                ->where('proyecto_id', $proyectoId)
                ->where('codigo', $f['codigo'])
                ->exists();
            if ($existe) {
                continue;
            }

            DB::table('niveles_sla')->insert([
                'proyecto_id' => $proyectoId,
                'codigo' => $f['codigo'],
                'nombre' => $f['nombre'],
                'horas_resolucion' => $f['horas'],
                'activo' => true,
                'orden' => $f['orden'],
            ]);
        }
    }
}
