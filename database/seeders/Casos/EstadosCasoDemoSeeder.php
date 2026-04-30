<?php

declare(strict_types=1);

namespace Database\Seeders\Casos;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Estados de caso del proyecto demo (Cobranza).
 * Cuando existan proyectos de CX/Venta/Servicio, cada uno tendrá su seeder propio
 * con los estados específicos de su operación.
 */
final class EstadosCasoDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $filas = [
            ['codigo' => 'ABIERTO',     'nombre' => 'Abierto',           'activo' => true, 'es_terminal' => false, 'orden' => 10],
            ['codigo' => 'EN_TRABAJO',  'nombre' => 'En trabajo',        'activo' => true, 'es_terminal' => false, 'orden' => 20],
            ['codigo' => 'GESTIONADO',  'nombre' => 'Gestionado',        'activo' => true, 'es_terminal' => false, 'orden' => 30],
            ['codigo' => 'NEGOCIANDO',  'nombre' => 'En negociación',    'activo' => true, 'es_terminal' => false, 'orden' => 40],
            ['codigo' => 'PAGADO',      'nombre' => 'Pagado (cerrado)',  'activo' => true, 'es_terminal' => true,  'orden' => 50],
            ['codigo' => 'CASTIGADO',   'nombre' => 'Castigado',         'activo' => true, 'es_terminal' => true,  'orden' => 60],
            ['codigo' => 'CANCELADO',   'nombre' => 'Cancelado',         'activo' => true, 'es_terminal' => true,  'orden' => 70],
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
                'codigo' => $f['codigo'],
                'nombre' => $f['nombre'],
                'activo' => $f['activo'],
                'es_terminal' => $f['es_terminal'],
                'orden' => $f['orden'],
            ]);
        }
    }
}
