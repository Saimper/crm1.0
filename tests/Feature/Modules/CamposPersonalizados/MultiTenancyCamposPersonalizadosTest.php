<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\CamposPersonalizados;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class MultiTenancyCamposPersonalizadosTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_campos_personalizados_aislados_entre_proyectos(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCx();

        DB::table('campos_personalizados')->insert([
            'proyecto_id' => $proyectoB->id,
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
            ->where('proyecto_id', $proyectoA->id)
            ->where('codigo', 'F34C_CP_B')
            ->exists();
        $this->assertFalse($existeEnA);

        $existeEnB = DB::table('campos_personalizados')
            ->where('proyecto_id', $proyectoB->id)
            ->where('codigo', 'F34C_CP_B')
            ->exists();
        $this->assertTrue($existeEnB);
    }
}
