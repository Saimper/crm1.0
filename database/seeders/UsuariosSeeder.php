<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Usuarios\PermisosSeeder;
use Database\Seeders\Usuarios\RolesSeeder;
use Database\Seeders\Usuarios\RolPermisoSeeder;
use Database\Seeders\Usuarios\UsuarioAdminGlobalSeeder;
use Illuminate\Database\Seeder;

final class UsuariosSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            PermisosSeeder::class,
            RolPermisoSeeder::class,
            UsuarioAdminGlobalSeeder::class,
        ]);
    }
}
