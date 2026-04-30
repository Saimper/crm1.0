<?php

declare(strict_types=1);

namespace Database\Seeders\Tenancy;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MandantesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $filas = [
            [
                'codigo' => 'BPO_DEMO',
                'nombre' => 'BPO Demo Corp',
                'documento' => '0000000000001',
            ],
        ];

        foreach ($filas as $row) {
            if (DB::table('mandantes')->where('codigo', $row['codigo'])->exists()) {
                continue;
            }

            DB::table('mandantes')->insert([
                'public_id' => (string) Str::ulid(),
                'codigo' => $row['codigo'],
                'nombre' => $row['nombre'],
                'documento' => $row['documento'],
                'activo' => true,
            ]);
        }
    }
}
