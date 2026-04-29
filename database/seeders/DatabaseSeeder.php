<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            CatalogosGlobalesSeeder::class,
            TenancySeeder::class,
            UsuariosSeeder::class,
            CasosSeeder::class,
            GestionesSeeder::class,
            PersonasSeeder::class,
            ContactosSeeder::class,
            CobranzaSeeder::class,
            CxSeeder::class,
            VentaSeeder::class,
            ServicioSeeder::class,
            CampanasSeeder::class,
            AsignacionesSeeder::class,
        ]);
    }
}
