<?php

declare(strict_types=1);

namespace Database\Seeders\Servicio;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class TiposAccionServicioDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'SERVICIO_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $filas = [
            ['codigo' => 'INSTALACION',    'nombre' => 'Instalación',           'duracion' => 4, 'orden' => 10],
            ['codigo' => 'MANTENIMIENTO',  'nombre' => 'Mantenimiento',         'duracion' => 2, 'orden' => 20],
            ['codigo' => 'REPARACION',     'nombre' => 'Reparación',            'duracion' => 3, 'orden' => 30],
            ['codigo' => 'CONFIGURACION',  'nombre' => 'Configuración',         'duracion' => 1, 'orden' => 40],
            ['codigo' => 'DESINSTALACION', 'nombre' => 'Desinstalación',        'duracion' => 2, 'orden' => 50],
            ['codigo' => 'REVISION',       'nombre' => 'Revisión técnica',      'duracion' => 1, 'orden' => 60],
        ];

        foreach ($filas as $f) {
            if (DB::table('tipos_accion_servicio')->where('proyecto_id', $proyectoId)->where('codigo', $f['codigo'])->exists()) {
                continue;
            }
            DB::table('tipos_accion_servicio')->insert([
                'proyecto_id'             => $proyectoId,
                'codigo'                  => $f['codigo'],
                'nombre'                  => $f['nombre'],
                'duracion_estimada_horas' => $f['duracion'],
                'activo'                  => true,
                'orden'                   => $f['orden'],
            ]);
        }
    }
}
