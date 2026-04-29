<?php

declare(strict_types=1);

namespace Database\Seeders\Venta;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class EtapasEmbudoDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'VENTA_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $filas = [
            ['codigo' => 'PROSPECCION',   'nombre' => 'Prospección',    'nivel' => 1, 'probabilidad' => 10, 'orden' => 10],
            ['codigo' => 'CALIFICACION',  'nombre' => 'Calificación',   'nivel' => 2, 'probabilidad' => 25, 'orden' => 20],
            ['codigo' => 'PROPUESTA',     'nombre' => 'Propuesta',      'nivel' => 3, 'probabilidad' => 50, 'orden' => 30],
            ['codigo' => 'NEGOCIACION',   'nombre' => 'Negociación',    'nivel' => 4, 'probabilidad' => 75, 'orden' => 40],
            ['codigo' => 'CIERRE',        'nombre' => 'Cierre',         'nivel' => 5, 'probabilidad' => 95, 'orden' => 50],
        ];

        foreach ($filas as $f) {
            if (DB::table('etapas_embudo')->where('proyecto_id', $proyectoId)->where('codigo', $f['codigo'])->exists()) {
                continue;
            }
            DB::table('etapas_embudo')->insert([
                'proyecto_id'         => $proyectoId,
                'codigo'              => $f['codigo'],
                'nombre'              => $f['nombre'],
                'nivel'               => $f['nivel'],
                'probabilidad_cierre' => $f['probabilidad'],
                'activo'              => true,
                'orden'               => $f['orden'],
            ]);
        }
    }
}
