<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Catalogos;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class MultiTenancyCatalogosTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_resultados_aislados_por_proyecto(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCx();

        DB::table('resultados')->insert([
            'proyecto_id' => $proyectoB->id,
            'codigo' => 'F34C_R_B',
            'nombre' => 'Resultado B exclusivo',
            'orden' => 999,
            'activo' => true,
        ]);

        $existeEnA = DB::table('resultados')
            ->where('proyecto_id', $proyectoA->id)
            ->where('codigo', 'F34C_R_B')
            ->exists();
        $this->assertFalse($existeEnA);

        $this->assertTrue(
            DB::table('resultados')
                ->where('proyecto_id', $proyectoB->id)
                ->where('codigo', 'F34C_R_B')
                ->exists()
        );
    }

    public function test_tipos_gestion_aislados_por_proyecto(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCx();

        DB::table('tipos_gestion')->insert([
            'proyecto_id' => $proyectoB->id,
            'codigo' => 'F34C_TG_B',
            'nombre' => 'TG B',
            'orden' => 999,
            'activo' => true,
        ]);

        $this->assertFalse(
            DB::table('tipos_gestion')->where('proyecto_id', $proyectoA->id)
                ->where('codigo', 'F34C_TG_B')->exists()
        );
        $this->assertTrue(
            DB::table('tipos_gestion')->where('proyecto_id', $proyectoB->id)
                ->where('codigo', 'F34C_TG_B')->exists()
        );
    }
}
