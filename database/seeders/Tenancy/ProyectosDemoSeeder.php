<?php

declare(strict_types=1);

namespace Database\Seeders\Tenancy;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProyectosDemoSeeder extends Seeder
{
    public function run(): void
    {
        $mandanteId = (int) DB::table('mandantes')->where('codigo', 'BPO_DEMO')->value('id');
        if ($mandanteId === 0) {
            return;
        }

        $filas = [
            [
                'codigo' => 'COBRANZA_DEMO_2026',
                'nombre' => 'Cobranza Demo 2026',
                'descripcion' => 'Proyecto demo de cobranza para validar el refactor v2.',
                'tipo_operacion' => 'cobranza',
                'fecha_inicio' => '2026-04-01',
                'fecha_fin' => '2026-12-31',
            ],
            [
                'codigo' => 'SOPORTE_DEMO_2026',
                'nombre' => 'Soporte Demo 2026',
                'descripcion' => 'Proyecto demo de CX/tickets para validar el segundo tipo de operación (Fase 3).',
                'tipo_operacion' => 'cx',
                'fecha_inicio' => '2026-04-15',
                'fecha_fin' => '2026-12-31',
            ],
            [
                'codigo' => 'VENTA_DEMO_2026',
                'nombre' => 'Venta Demo 2026',
                'descripcion' => 'Proyecto demo de venta outbound para validar el tercer tipo de operación (Fase 4).',
                'tipo_operacion' => 'venta',
                'fecha_inicio' => '2026-04-18',
                'fecha_fin' => '2026-12-31',
            ],
            [
                'codigo' => 'SERVICIO_DEMO_2026',
                'nombre' => 'Servicio Técnico Demo 2026',
                'descripcion' => 'Proyecto demo de servicio técnico para validar el cuarto y último tipo de operación (Fase 5).',
                'tipo_operacion' => 'servicio',
                'fecha_inicio' => '2026-04-20',
                'fecha_fin' => '2026-12-31',
            ],
        ];

        foreach ($filas as $row) {
            $existe = DB::table('proyectos')
                ->where('mandante_id', $mandanteId)
                ->where('codigo', $row['codigo'])
                ->exists();
            if ($existe) {
                continue;
            }

            DB::table('proyectos')->insert([
                'public_id' => (string) Str::ulid(),
                'mandante_id' => $mandanteId,
                'codigo' => $row['codigo'],
                'nombre' => $row['nombre'],
                'descripcion' => $row['descripcion'],
                'tipo_operacion' => $row['tipo_operacion'],
                'activo' => true,
                'fecha_inicio' => $row['fecha_inicio'],
                'fecha_fin' => $row['fecha_fin'],
            ]);
        }
    }
}
