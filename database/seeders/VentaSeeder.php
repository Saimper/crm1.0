<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Venta\AsignacionesVentaDemoSeeder;
use Database\Seeders\Venta\CampanaVentaDemoSeeder;
use Database\Seeders\Venta\EstadosCasoVentaDemoSeeder;
use Database\Seeders\Venta\EtapasEmbudoDemoSeeder;
use Database\Seeders\Venta\GestionesCatalogosVentaDemoSeeder;
use Database\Seeders\Venta\LeadsVentaDemoSeeder;
use Database\Seeders\Venta\PersonasVentaDemoSeeder;
use Database\Seeders\Venta\ProductosVentaDemoSeeder;
use Illuminate\Database\Seeder;

/**
 * Agrupador de seeders específicos del proyecto demo Venta (Fase 4).
 */
final class VentaSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            EstadosCasoVentaDemoSeeder::class,
            ProductosVentaDemoSeeder::class,
            EtapasEmbudoDemoSeeder::class,
            GestionesCatalogosVentaDemoSeeder::class,
            PersonasVentaDemoSeeder::class,
            LeadsVentaDemoSeeder::class,
            CampanaVentaDemoSeeder::class,
            AsignacionesVentaDemoSeeder::class,
        ]);
    }
}
