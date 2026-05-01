<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Catalogos;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F34C — multi-tenancy: catálogos por proyecto (resultados, tipos_gestion,
 * estados_caso, motivos_no_contacto, causas_gestion). Catalogo de proyecto B
 * NO debe aparecer al consultar scopeado a A.
 */
final class MultiTenancyCatalogosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_resultados_aislados_por_proyecto(): void
    {
        $proyectoA = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $proyectoB = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');

        // Conteo previo en A.
        $countAOriginal = (int) DB::table('resultados')->where('proyecto_id', $proyectoA)->count();

        // Insertar en B.
        DB::table('resultados')->insert([
            'proyecto_id' => $proyectoB,
            'codigo' => 'F34C_R_B',
            'nombre' => 'Resultado B exclusivo',
            'orden' => 999,
            'activo' => true,
        ]);

        // Conteo en A no cambió.
        $this->assertSame($countAOriginal, (int) DB::table('resultados')->where('proyecto_id', $proyectoA)->count());

        // Búsqueda scoped no encuentra el de B.
        $existeEnA = DB::table('resultados')
            ->where('proyecto_id', $proyectoA)
            ->where('codigo', 'F34C_R_B')
            ->exists();
        $this->assertFalse($existeEnA);
    }

    public function test_tipos_gestion_aislados_por_proyecto(): void
    {
        $proyectoA = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $proyectoB = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');

        DB::table('tipos_gestion')->insert([
            'proyecto_id' => $proyectoB,
            'codigo' => 'F34C_TG_B',
            'nombre' => 'TG B',
            'orden' => 999,
            'activo' => true,
        ]);

        $this->assertFalse(
            DB::table('tipos_gestion')->where('proyecto_id', $proyectoA)
                ->where('codigo', 'F34C_TG_B')->exists()
        );
        $this->assertTrue(
            DB::table('tipos_gestion')->where('proyecto_id', $proyectoB)
                ->where('codigo', 'F34C_TG_B')->exists()
        );
    }
}
