<?php

declare(strict_types=1);

namespace Database\Seeders\Cobranza;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Causas específicas para operación de cobranza (causa de mora).
 * Siembran en el catálogo genérico `causas_gestion` (ver migración) con metadata→tipo = 'mora'.
 */
final class CausasMoraDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $filas = [
            ['codigo' => 'DESEMPLEO',          'nombre' => 'Desempleo'],
            ['codigo' => 'INGRESOS_BAJOS',     'nombre' => 'Ingresos bajos'],
            ['codigo' => 'OLVIDO_CUOTA',       'nombre' => 'Olvido de fecha de pago'],
            ['codigo' => 'EMERGENCIA_MEDICA',  'nombre' => 'Emergencia médica'],
            ['codigo' => 'VIAJE',              'nombre' => 'Viaje fuera de la ciudad'],
            ['codigo' => 'PROBLEMA_BANCO',     'nombre' => 'Problema con el banco'],
            ['codigo' => 'DESACUERDO_MONTO',   'nombre' => 'Desacuerdo con el monto'],
            ['codigo' => 'OTRAS',              'nombre' => 'Otras causas'],
        ];

        $orden = 10;
        foreach ($filas as $f) {
            $existe = DB::table('causas_gestion')
                ->where('proyecto_id', $proyectoId)
                ->where('codigo', $f['codigo'])
                ->exists();
            if (! $existe) {
                DB::table('causas_gestion')->insert([
                    'proyecto_id' => $proyectoId,
                    'codigo' => $f['codigo'],
                    'nombre' => $f['nombre'],
                    'activo' => true,
                    'orden' => $orden,
                    'metadata' => json_encode(['tipo' => 'mora']),
                ]);
            }
            $orden += 10;
        }
    }
}
