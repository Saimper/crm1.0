<?php

declare(strict_types=1);

namespace Database\Seeders\Servicio;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class EstadosTecnicosDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'SERVICIO_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $filas = [
            ['codigo' => 'SIN_AGENDA',      'nombre' => 'Sin agendar',          'orden' => 10],
            ['codigo' => 'AGENDADO',        'nombre' => 'Agendado',             'orden' => 20],
            ['codigo' => 'EN_TERRENO',      'nombre' => 'Técnico en terreno',   'orden' => 30],
            ['codigo' => 'EN_EJECUCION',    'nombre' => 'En ejecución',         'orden' => 40],
            ['codigo' => 'COMPLETADO_OK',   'nombre' => 'Completado OK',        'orden' => 50],
            ['codigo' => 'FALLIDO_CLIENTE', 'nombre' => 'Fallido (cliente)',    'orden' => 60],
            ['codigo' => 'FALLIDO_TECNICO', 'nombre' => 'Fallido (técnico)',    'orden' => 70],
        ];

        foreach ($filas as $f) {
            if (DB::table('estados_tecnicos')->where('proyecto_id', $proyectoId)->where('codigo', $f['codigo'])->exists()) {
                continue;
            }
            DB::table('estados_tecnicos')->insert([
                'proyecto_id' => $proyectoId,
                'codigo' => $f['codigo'],
                'nombre' => $f['nombre'],
                'activo' => true,
                'orden' => $f['orden'],
            ]);
        }
    }
}
