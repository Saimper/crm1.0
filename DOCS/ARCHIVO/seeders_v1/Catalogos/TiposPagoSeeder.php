<?php

declare(strict_types=1);

namespace Database\Seeders\Catalogos;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class TiposPagoSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['codigo' => 'EFECTIVO',       'nombre' => 'Efectivo',              'activo' => true, 'orden' => 10, 'metadata' => null],
            ['codigo' => 'TRANSFERENCIA',  'nombre' => 'Transferencia bancaria','activo' => true, 'orden' => 20, 'metadata' => null],
            ['codigo' => 'DEPOSITO',       'nombre' => 'Depósito bancario',     'activo' => true, 'orden' => 30, 'metadata' => null],
            ['codigo' => 'TARJETA',        'nombre' => 'Tarjeta',               'activo' => true, 'orden' => 40, 'metadata' => null],
            ['codigo' => 'CHEQUE',         'nombre' => 'Cheque',                'activo' => true, 'orden' => 50, 'metadata' => null],
        ];

        DB::table('tipos_pago')->upsert(
            $rows,
            ['codigo'],
            ['nombre', 'activo', 'orden', 'metadata'],
        );
    }
}
