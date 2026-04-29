<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Campanas\CampanaDemoSeeder;
use Illuminate\Database\Seeder;

final class CampanasSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CampanaDemoSeeder::class,
        ]);
    }
}
