<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\EntidadesConfigurables;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * F34C — multi-tenancy: entidades configurables aisladas por proyecto.
 */
final class MultiTenancyEntidadesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_entidades_configurables_aisladas_entre_proyectos(): void
    {
        $proyectoA = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $proyectoB = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');

        DB::table('entidades_configurables')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoB,
            'codigo' => 'F34C_ENT_B',
            'nombre' => 'Entidad B',
            'activo' => true,
        ]);

        $this->assertFalse(
            DB::table('entidades_configurables')
                ->where('proyecto_id', $proyectoA)
                ->where('codigo', 'F34C_ENT_B')
                ->exists()
        );
        $this->assertTrue(
            DB::table('entidades_configurables')
                ->where('proyecto_id', $proyectoB)
                ->where('codigo', 'F34C_ENT_B')
                ->exists()
        );
    }
}
