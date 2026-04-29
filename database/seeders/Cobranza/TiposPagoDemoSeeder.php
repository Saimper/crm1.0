<?php

declare(strict_types=1);

namespace Database\Seeders\Cobranza;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class TiposPagoDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $filas = [
            ['codigo' => 'EFECTIVO',       'nombre' => 'Efectivo',       'orden' => 10],
            ['codigo' => 'TRANSFERENCIA',  'nombre' => 'Transferencia',  'orden' => 20],
            ['codigo' => 'CHEQUE',         'nombre' => 'Cheque',         'orden' => 30],
            ['codigo' => 'TARJETA',        'nombre' => 'Tarjeta',        'orden' => 40],
            ['codigo' => 'DEBITO_AUTO',    'nombre' => 'Débito automático', 'orden' => 50],
        ];

        foreach ($filas as $f) {
            $existe = DB::table('tipos_pago')
                ->where('proyecto_id', $proyectoId)
                ->where('codigo', $f['codigo'])
                ->exists();
            if ($existe) {
                continue;
            }

            DB::table('tipos_pago')->insert([
                'proyecto_id' => $proyectoId,
                'codigo'      => $f['codigo'],
                'nombre'      => $f['nombre'],
                'activo'      => true,
                'orden'       => $f['orden'],
            ]);
        }
    }
}
