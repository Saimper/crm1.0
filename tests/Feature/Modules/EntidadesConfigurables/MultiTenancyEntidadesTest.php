<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\EntidadesConfigurables;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class MultiTenancyEntidadesTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_entidades_configurables_aisladas_entre_proyectos(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCx();

        DB::table('entidades_configurables')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoB->id,
            'codigo' => 'F34C_ENT_B',
            'nombre' => 'Entidad B',
            'activo' => true,
        ]);

        $this->assertFalse(
            DB::table('entidades_configurables')
                ->where('proyecto_id', $proyectoA->id)
                ->where('codigo', 'F34C_ENT_B')
                ->exists()
        );
        $this->assertTrue(
            DB::table('entidades_configurables')
                ->where('proyecto_id', $proyectoB->id)
                ->where('codigo', 'F34C_ENT_B')
                ->exists()
        );
    }
}
