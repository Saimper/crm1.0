<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Servicio\AsignacionesServicioDemoSeeder;
use Database\Seeders\Servicio\CampanaServicioDemoSeeder;
use Database\Seeders\Servicio\CasosServicioDemoSeeder;
use Database\Seeders\Servicio\EstadosCasoServicioDemoSeeder;
use Database\Seeders\Servicio\EstadosTecnicosDemoSeeder;
use Database\Seeders\Servicio\GestionesCatalogosServicioDemoSeeder;
use Database\Seeders\Servicio\PersonasServicioDemoSeeder;
use Database\Seeders\Servicio\TiposAccionServicioDemoSeeder;
use Illuminate\Database\Seeder;

/**
 * Agrupador de seeders específicos del proyecto demo Servicio (Fase 5).
 */
final class ServicioSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            EstadosCasoServicioDemoSeeder::class,
            TiposAccionServicioDemoSeeder::class,
            EstadosTecnicosDemoSeeder::class,
            GestionesCatalogosServicioDemoSeeder::class,
            PersonasServicioDemoSeeder::class,
            CasosServicioDemoSeeder::class,
            CampanaServicioDemoSeeder::class,
            AsignacionesServicioDemoSeeder::class,
        ]);
    }
}
