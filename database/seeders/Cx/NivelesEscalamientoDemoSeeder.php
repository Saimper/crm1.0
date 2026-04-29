<?php

declare(strict_types=1);

namespace Database\Seeders\Cx;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class NivelesEscalamientoDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $filas = [
            ['codigo' => 'N1', 'nombre' => 'Nivel 1 (gestor)',          'nivel' => 1, 'orden' => 10],
            ['codigo' => 'N2', 'nombre' => 'Nivel 2 (supervisor)',      'nivel' => 2, 'orden' => 20],
            ['codigo' => 'N3', 'nombre' => 'Nivel 3 (técnico senior)',  'nivel' => 3, 'orden' => 30],
            ['codigo' => 'N4', 'nombre' => 'Nivel 4 (mandante)',        'nivel' => 4, 'orden' => 40],
        ];

        foreach ($filas as $f) {
            $existe = DB::table('niveles_escalamiento')
                ->where('proyecto_id', $proyectoId)
                ->where('codigo', $f['codigo'])
                ->exists();
            if ($existe) {
                continue;
            }

            DB::table('niveles_escalamiento')->insert([
                'proyecto_id' => $proyectoId,
                'codigo'      => $f['codigo'],
                'nombre'      => $f['nombre'],
                'nivel'       => $f['nivel'],
                'activo'      => true,
                'orden'       => $f['orden'],
            ]);
        }
    }
}
