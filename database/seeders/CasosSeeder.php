<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Casos\EstadosCasoDemoSeeder;
use Illuminate\Database\Seeder;

final class CasosSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            EstadosCasoDemoSeeder::class,
        ]);
    }
}
