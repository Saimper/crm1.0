<?php

declare(strict_types=1);

namespace Database\Seeders\Gestiones;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class CanalesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['codigo' => 'TELEFONO', 'nombre' => 'Teléfono',         'activo' => true, 'orden' => 10, 'metadata' => null],
            ['codigo' => 'WHATSAPP', 'nombre' => 'WhatsApp',         'activo' => true, 'orden' => 20, 'metadata' => null],
            ['codigo' => 'SMS',      'nombre' => 'SMS',              'activo' => true, 'orden' => 30, 'metadata' => null],
            ['codigo' => 'CORREO',   'nombre' => 'Correo electrónico', 'activo' => true, 'orden' => 40, 'metadata' => null],
            ['codigo' => 'VISITA',   'nombre' => 'Visita',           'activo' => true, 'orden' => 50, 'metadata' => null],
            ['codigo' => 'OFICINA',  'nombre' => 'Oficina',          'activo' => true, 'orden' => 60, 'metadata' => null],
        ];

        DB::table('canales')->upsert($rows, ['codigo'], ['nombre', 'activo', 'orden', 'metadata']);
    }
}
