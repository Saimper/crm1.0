<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Gestiones;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * F34C — multi-tenancy: gestiones aisladas por proyecto.
 */
final class MultiTenancyGestionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_gestiones_aisladas_entre_proyectos(): void
    {
        $proyectoA = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $proyectoB = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');

        $casoB = (object) DB::table('casos')->where('proyecto_id', $proyectoB)->first();
        $usuarioId = (int) DB::table('users')->first()->id;
        $tipoGestionB = (int) DB::table('tipos_gestion')->where('proyecto_id', $proyectoB)->value('id');
        $resultadoB = (int) DB::table('resultados')->where('proyecto_id', $proyectoB)->value('id');
        $canalId = (int) DB::table('canales')->value('id');

        DB::table('gestiones')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoB,
            'caso_id' => $casoB->id,
            'persona_id' => $casoB->persona_id,
            'usuario_id' => $usuarioId,
            'canal_id' => $canalId,
            'tipo_gestion_id' => $tipoGestionB,
            'resultado_id' => $resultadoB,
            'notas' => 'Gestión exclusiva B',
            'creada_en' => Carbon::now(),
        ]);

        $existeEnA = DB::table('gestiones')
            ->where('proyecto_id', $proyectoA)
            ->where('notas', 'Gestión exclusiva B')
            ->exists();
        $this->assertFalse($existeEnA);

        $existeEnB = DB::table('gestiones')
            ->where('proyecto_id', $proyectoB)
            ->where('notas', 'Gestión exclusiva B')
            ->exists();
        $this->assertTrue($existeEnB);
    }
}
