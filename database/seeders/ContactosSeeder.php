<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Contactos\ContactosDemoSeeder;
use Illuminate\Database\Seeder;

final class ContactosSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ContactosDemoSeeder::class,
        ]);
    }
}
