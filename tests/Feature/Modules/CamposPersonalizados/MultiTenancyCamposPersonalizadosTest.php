<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\CamposPersonalizados;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F34C — multi-tenancy: definiciones de campos personalizados aisladas
 * por proyecto. Definición creada en proyecto B no debe aparecer
 * scopeada a A.
 */
final class MultiTenancyCamposPersonalizadosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_campos_personalizados_aislados_entre_proyectos(): void
    {
        $proyectoA = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $proyectoB = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');

        DB::table('campos_personalizados')->insert([
            'proyecto_id' => $proyectoB,
            'ambito' => 'gestion',
            'ambito_id' => 1,
            'codigo' => 'F34C_CP_B',
            'etiqueta' => 'Campo B',
            'tipo' => 'texto_corto',
            'obligatorio' => false,
            'reglas' => json_encode([]),
            'orden' => 1,
            'activo' => true,
        ]);

        $existeEnA = DB::table('campos_personalizados')
            ->where('proyecto_id', $proyectoA)
            ->where('codigo', 'F34C_CP_B')
            ->exists();
        $this->assertFalse($existeEnA);

        $existeEnB = DB::table('campos_personalizados')
            ->where('proyecto_id', $proyectoB)
            ->where('codigo', 'F34C_CP_B')
            ->exists();
        $this->assertTrue($existeEnB);
    }
}
