<?php

declare(strict_types=1);

namespace Database\Seeders\Cx;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class PrioridadesTicketDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $filas = [
            ['codigo' => 'BAJA',    'nombre' => 'Baja',    'peso' => 10, 'orden' => 10],
            ['codigo' => 'MEDIA',   'nombre' => 'Media',   'peso' => 20, 'orden' => 20],
            ['codigo' => 'ALTA',    'nombre' => 'Alta',    'peso' => 30, 'orden' => 30],
            ['codigo' => 'URGENTE', 'nombre' => 'Urgente', 'peso' => 40, 'orden' => 40],
        ];

        foreach ($filas as $f) {
            $existe = DB::table('prioridades_ticket')
                ->where('proyecto_id', $proyectoId)
                ->where('codigo', $f['codigo'])
                ->exists();
            if ($existe) {
                continue;
            }

            DB::table('prioridades_ticket')->insert([
                'proyecto_id' => $proyectoId,
                'codigo'      => $f['codigo'],
                'nombre'      => $f['nombre'],
                'peso'        => $f['peso'],
                'activo'      => true,
                'orden'       => $f['orden'],
            ]);
        }
    }
}
