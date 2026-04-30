<?php

declare(strict_types=1);

namespace Database\Seeders\Venta;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class ProductosVentaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'VENTA_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $filas = [
            ['codigo' => 'SEGURO_VIDA',      'nombre' => 'Seguro de vida',              'orden' => 10],
            ['codigo' => 'SEGURO_AUTO',      'nombre' => 'Seguro de auto',              'orden' => 20],
            ['codigo' => 'TARJETA_PREMIUM',  'nombre' => 'Tarjeta de crédito Premium',  'orden' => 30],
            ['codigo' => 'PRESTAMO_CONSUMO', 'nombre' => 'Préstamo de consumo',         'orden' => 40],
            ['codigo' => 'PLAN_AHORRO',      'nombre' => 'Plan de ahorro',              'orden' => 50],
        ];

        foreach ($filas as $f) {
            if (DB::table('productos_venta')->where('proyecto_id', $proyectoId)->where('codigo', $f['codigo'])->exists()) {
                continue;
            }
            DB::table('productos_venta')->insert([
                'proyecto_id' => $proyectoId,
                'codigo' => $f['codigo'],
                'nombre' => $f['nombre'],
                'activo' => true,
                'orden' => $f['orden'],
            ]);
        }
    }
}
