<?php

declare(strict_types=1);

namespace Database\Seeders\Servicio;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class EstadosCasoServicioDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'SERVICIO_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $filas = [
            ['codigo' => 'PENDIENTE',   'nombre' => 'Pendiente',     'es_terminal' => false, 'orden' => 10],
            ['codigo' => 'AGENDADO',    'nombre' => 'Agendado',      'es_terminal' => false, 'orden' => 20],
            ['codigo' => 'EN_CURSO',    'nombre' => 'En curso',      'es_terminal' => false, 'orden' => 30],
            ['codigo' => 'COMPLETADO',  'nombre' => 'Completado',    'es_terminal' => true,  'orden' => 40],
            ['codigo' => 'FALLIDO',     'nombre' => 'Fallido',       'es_terminal' => true,  'orden' => 50],
            ['codigo' => 'CANCELADO',   'nombre' => 'Cancelado',     'es_terminal' => true,  'orden' => 60],
        ];

        foreach ($filas as $f) {
            if (DB::table('estados_caso')->where('proyecto_id', $proyectoId)->where('codigo', $f['codigo'])->exists()) {
                continue;
            }

            DB::table('estados_caso')->insert([
                'proyecto_id' => $proyectoId,
                'codigo' => $f['codigo'],
                'nombre' => $f['nombre'],
                'activo' => true,
                'es_terminal' => $f['es_terminal'],
                'orden' => $f['orden'],
            ]);
        }
    }
}
