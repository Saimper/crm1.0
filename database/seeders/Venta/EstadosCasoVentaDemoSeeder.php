<?php

declare(strict_types=1);

namespace Database\Seeders\Venta;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class EstadosCasoVentaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'VENTA_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $filas = [
            ['codigo' => 'NUEVO',            'nombre' => 'Nuevo',                 'es_terminal' => false, 'orden' => 10],
            ['codigo' => 'CALIFICADO',       'nombre' => 'Calificado',            'es_terminal' => false, 'orden' => 20],
            ['codigo' => 'EN_CONTACTO',      'nombre' => 'En contacto',           'es_terminal' => false, 'orden' => 30],
            ['codigo' => 'PROPUESTA',        'nombre' => 'Propuesta enviada',     'es_terminal' => false, 'orden' => 40],
            ['codigo' => 'NEGOCIACION',      'nombre' => 'En negociación',        'es_terminal' => false, 'orden' => 50],
            ['codigo' => 'CERRADO_GANADO',   'nombre' => 'Cerrado ganado',        'es_terminal' => true,  'orden' => 60],
            ['codigo' => 'CERRADO_PERDIDO',  'nombre' => 'Cerrado perdido',       'es_terminal' => true,  'orden' => 70],
            ['codigo' => 'CANCELADO',        'nombre' => 'Cancelado',             'es_terminal' => true,  'orden' => 80],
        ];

        foreach ($filas as $f) {
            if (DB::table('estados_caso')->where('proyecto_id', $proyectoId)->where('codigo', $f['codigo'])->exists()) {
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
