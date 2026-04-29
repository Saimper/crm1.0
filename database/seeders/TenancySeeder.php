<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Tenancy\CarterasDemoSeeder;
use Database\Seeders\Tenancy\MandantesDemoSeeder;
use Database\Seeders\Tenancy\ProyectosDemoSeeder;
use Illuminate\Database\Seeder;

final class TenancySeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            MandantesDemoSeeder::class,
            ProyectosDemoSeeder::class,
            CarterasDemoSeeder::class,
        ]);
    }
}
