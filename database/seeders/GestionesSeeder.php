<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Gestiones\CanalesSeeder;
use Database\Seeders\Gestiones\GestionesCatalogosDemoSeeder;
use Illuminate\Database\Seeder;

final class GestionesSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CanalesSeeder::class,                 // global
            GestionesCatalogosDemoSeeder::class,  // scoped al proyecto demo
        ]);
    }
}
