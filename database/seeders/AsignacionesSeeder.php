<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Asignaciones\AsignacionesDemoSeeder;
use Illuminate\Database\Seeder;

final class AsignacionesSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AsignacionesDemoSeeder::class,
        ]);
    }
}
