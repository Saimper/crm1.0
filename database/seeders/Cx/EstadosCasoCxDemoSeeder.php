<?php

declare(strict_types=1);

namespace Database\Seeders\Cx;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Estados de caso específicos para el proyecto demo CX (Soporte).
 */
final class EstadosCasoCxDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $filas = [
            ['codigo' => 'ABIERTO',    'nombre' => 'Abierto',      'es_terminal' => false, 'orden' => 10],
            ['codigo' => 'EN_PROCESO', 'nombre' => 'En proceso',   'es_terminal' => false, 'orden' => 20],
            ['codigo' => 'ESCALADO',   'nombre' => 'Escalado',     'es_terminal' => false, 'orden' => 30],
            ['codigo' => 'ESPERA_CLIENTE', 'nombre' => 'En espera del cliente', 'es_terminal' => false, 'orden' => 40],
            ['codigo' => 'RESUELTO',   'nombre' => 'Resuelto',     'es_terminal' => true,  'orden' => 50],
            ['codigo' => 'CERRADO',    'nombre' => 'Cerrado',      'es_terminal' => true,  'orden' => 60],
            ['codigo' => 'CANCELADO',  'nombre' => 'Cancelado',    'es_terminal' => true,  'orden' => 70],
        ];

        foreach ($filas as $f) {
            $existe = DB::table('estados_caso')
                ->where('proyecto_id', $proyectoId)
                ->where('codigo', $f['codigo'])
                ->exists();
            if ($existe) {
                continue;
            }

            DB::table('estados_caso')->insert([
                'proyecto_id' => $proyectoId,
                'codigo'      => $f['codigo'],
                'nombre'      => $f['nombre'],
                'activo'      => true,
                'es_terminal' => $f['es_terminal'],
                'orden'       => $f['orden'],
            ]);
        }
    }
}
