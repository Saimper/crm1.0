<?php

declare(strict_types=1);

namespace Database\Seeders\Catalogos;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class TiposGestionSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['codigo' => 'LLAMADA_SALIENTE', 'nombre' => 'Llamada saliente',    'activo' => true, 'orden' => 10, 'metadata' => null],
            ['codigo' => 'LLAMADA_ENTRANTE', 'nombre' => 'Llamada entrante',    'activo' => true, 'orden' => 20, 'metadata' => null],
            ['codigo' => 'VISITA',           'nombre' => 'Visita domiciliaria', 'activo' => true, 'orden' => 30, 'metadata' => null],
            ['codigo' => 'WHATSAPP',         'nombre' => 'WhatsApp',            'activo' => true, 'orden' => 40, 'metadata' => null],
            ['codigo' => 'SMS',              'nombre' => 'SMS',                 'activo' => true, 'orden' => 50, 'metadata' => null],
            ['codigo' => 'CORREO',           'nombre' => 'Correo electrónico',  'activo' => true, 'orden' => 60, 'metadata' => null],
            ['codigo' => 'NOTA',             'nombre' => 'Nota interna',        'activo' => true, 'orden' => 90, 'metadata' => null],
        ];

        DB::table('tipos_gestion')->upsert(
            $rows,
            ['codigo'],
            ['nombre', 'activo', 'orden', 'metadata'],
        );
    }
}
