<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Cx\AsignacionesCxDemoSeeder;
use Database\Seeders\Cx\CampanaCxDemoSeeder;
use Database\Seeders\Cx\CategoriasTicketDemoSeeder;
use Database\Seeders\Cx\EstadosCasoCxDemoSeeder;
use Database\Seeders\Cx\GestionesCatalogosCxDemoSeeder;
use Database\Seeders\Cx\NivelesEscalamientoDemoSeeder;
use Database\Seeders\Cx\NivelesSlaDemoSeeder;
use Database\Seeders\Cx\PersonasCxDemoSeeder;
use Database\Seeders\Cx\PrioridadesTicketDemoSeeder;
use Database\Seeders\Cx\TicketsCxDemoSeeder;
use Illuminate\Database\Seeder;

/**
 * Agrupador de seeders específicos del proyecto demo CX (Fase 3).
 */
final class CxSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            EstadosCasoCxDemoSeeder::class,
            CategoriasTicketDemoSeeder::class,
            PrioridadesTicketDemoSeeder::class,
            NivelesSlaDemoSeeder::class,
            NivelesEscalamientoDemoSeeder::class,
            GestionesCatalogosCxDemoSeeder::class,
            PersonasCxDemoSeeder::class,
            TicketsCxDemoSeeder::class,
            CampanaCxDemoSeeder::class,
            AsignacionesCxDemoSeeder::class,
        ]);
    }
}
