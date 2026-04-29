<?php

declare(strict_types=1);

namespace Database\Seeders\Cx;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class CategoriasTicketDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $filas = [
            ['codigo' => 'ACCESO',          'nombre' => 'Problemas de acceso',      'orden' => 10],
            ['codigo' => 'FACTURACION',     'nombre' => 'Facturación',              'orden' => 20],
            ['codigo' => 'SERVICIO',        'nombre' => 'Calidad de servicio',      'orden' => 30],
            ['codigo' => 'INSTALACION',     'nombre' => 'Instalación',              'orden' => 40],
            ['codigo' => 'RECLAMO',         'nombre' => 'Reclamo general',          'orden' => 50],
            ['codigo' => 'CONSULTA',        'nombre' => 'Consulta',                 'orden' => 60],
        ];

        foreach ($filas as $f) {
            $existe = DB::table('categorias_ticket')
                ->where('proyecto_id', $proyectoId)
                ->where('codigo', $f['codigo'])
                ->exists();
            if ($existe) {
                continue;
            }

            DB::table('categorias_ticket')->insert([
                'proyecto_id' => $proyectoId,
                'codigo'      => $f['codigo'],
                'nombre'      => $f['nombre'],
                'activo'      => true,
                'orden'       => $f['orden'],
            ]);
        }
    }
}
