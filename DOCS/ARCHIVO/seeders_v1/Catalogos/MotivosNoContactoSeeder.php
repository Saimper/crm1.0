<?php

declare(strict_types=1);

namespace Database\Seeders\Catalogos;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class MotivosNoContactoSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['codigo' => 'NO_RESPONDE',        'nombre' => 'No responde llamadas',           'activo' => true, 'orden' => 10, 'metadata' => null],
            ['codigo' => 'NUMERO_EQUIVOCADO',  'nombre' => 'Número equivocado',              'activo' => true, 'orden' => 20, 'metadata' => null],
            ['codigo' => 'FUERA_SERVICIO',     'nombre' => 'Teléfono fuera de servicio',     'activo' => true, 'orden' => 30, 'metadata' => null],
            ['codigo' => 'NO_DISPONIBLE',      'nombre' => 'Titular no disponible',          'activo' => true, 'orden' => 40, 'metadata' => null],
            ['codigo' => 'CAMBIO_DIRECCION',   'nombre' => 'Cambió de dirección',            'activo' => true, 'orden' => 50, 'metadata' => null],
            ['codigo' => 'NO_UBICADO',         'nombre' => 'No ubicado en dirección',        'activo' => true, 'orden' => 60, 'metadata' => null],
            ['codigo' => 'REHUSA_IDENTIFICAR', 'nombre' => 'Rehúsa identificarse',           'activo' => true, 'orden' => 70, 'metadata' => null],
        ];

        DB::table('motivos_no_contacto')->upsert(
            $rows,
            ['codigo'],
            ['nombre', 'activo', 'orden', 'metadata'],
        );
    }
}
