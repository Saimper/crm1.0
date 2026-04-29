<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Catalogos\CanalesSeeder;
use Database\Seeders\Catalogos\CarterasSeeder;
use Database\Seeders\Catalogos\CausasMoraSeeder;
use Database\Seeders\Catalogos\EstadosProductoSeeder;
use Database\Seeders\Catalogos\MotivosNoContactoSeeder;
use Database\Seeders\Catalogos\ResultadosSeeder;
use Database\Seeders\Catalogos\TiposGestionSeeder;
use Database\Seeders\Catalogos\TiposIdentificacionSeeder;
use Database\Seeders\Catalogos\TiposPagoSeeder;
use Database\Seeders\Catalogos\TramosMoraSeeder;
use Illuminate\Database\Seeder;

final class CatalogosSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TiposIdentificacionSeeder::class,
            TiposGestionSeeder::class,
            ResultadosSeeder::class,
            CausasMoraSeeder::class,
            CanalesSeeder::class,
            TiposPagoSeeder::class,
            EstadosProductoSeeder::class,
            MotivosNoContactoSeeder::class,
            CarterasSeeder::class,
            TramosMoraSeeder::class,
        ]);
    }
}
