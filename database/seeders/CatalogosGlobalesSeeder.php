<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Catalogos\EstadosBaseSistemaSeeder;
use Database\Seeders\Catalogos\MonedasSeeder;
use Database\Seeders\Catalogos\PaisesSeeder;
use Database\Seeders\Catalogos\TiposDocumentoSeeder;
use Database\Seeders\Catalogos\TiposIdentificacionSeeder;
use Illuminate\Database\Seeder;

final class CatalogosGlobalesSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PaisesSeeder::class,
            MonedasSeeder::class,
            TiposDocumentoSeeder::class,
            EstadosBaseSistemaSeeder::class,
            TiposIdentificacionSeeder::class,
        ]);
    }
}
