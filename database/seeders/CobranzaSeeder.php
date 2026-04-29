<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Cobranza\CamposPersonalizadosDemoSeeder;
use Database\Seeders\Cobranza\CasosCobranzaDemoSeeder;
use Database\Seeders\Cobranza\CausasMoraDemoSeeder;
use Database\Seeders\Cobranza\TiposPagoDemoSeeder;
use Database\Seeders\Cobranza\TramosMoraDemoSeeder;
use Illuminate\Database\Seeder;

final class CobranzaSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TramosMoraDemoSeeder::class,
            TiposPagoDemoSeeder::class,
            CausasMoraDemoSeeder::class,
            CasosCobranzaDemoSeeder::class,
            CamposPersonalizadosDemoSeeder::class,
        ]);
    }
}
