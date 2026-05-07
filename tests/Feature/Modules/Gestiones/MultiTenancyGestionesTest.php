<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Gestiones;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class MultiTenancyGestionesTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_gestiones_aisladas_entre_proyectos(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCx();

        $carteraB = $this->crearCarteraEn($proyectoB);
        $personaB = $this->crearPersonaEn($proyectoB);
        $estadoB = $this->crearEstadoCasoEn($proyectoB);
        $usuario = $this->crearGestor($proyectoB);

        $casoBId = (int) DB::table('casos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoB->id,
            'cartera_id' => $carteraB->id,
            'persona_id' => $personaB->id,
            'tipo_caso' => 'ticket_cx',
            'estado_caso_id' => $estadoB->id,
            'fecha_ingreso' => Carbon::now()->toDateString(),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        $tipoGestionBId = (int) DB::table('tipos_gestion')->insertGetId([
            'proyecto_id' => $proyectoB->id,
            'codigo' => 'TG_B',
            'nombre' => 'TG B',
            'orden' => 10,
            'activo' => true,
        ]);
        $resultadoBId = (int) DB::table('resultados')->insertGetId([
            'proyecto_id' => $proyectoB->id,
            'codigo' => 'R_B',
            'nombre' => 'R B',
            'orden' => 10,
            'activo' => true,
        ]);
        $canalId = (int) DB::table('canales')->value('id');

        DB::table('gestiones')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoB->id,
            'caso_id' => $casoBId,
            'persona_id' => $personaB->id,
            'usuario_id' => $usuario->id,
            'canal_id' => $canalId,
            'tipo_gestion_id' => $tipoGestionBId,
            'resultado_id' => $resultadoBId,
            'notas' => 'Gestión exclusiva B',
            'creada_en' => Carbon::now(),
        ]);

        $existeEnA = DB::table('gestiones')
            ->where('proyecto_id', $proyectoA->id)
            ->where('notas', 'Gestión exclusiva B')
            ->exists();
        $this->assertFalse($existeEnA);

        $existeEnB = DB::table('gestiones')
            ->where('proyecto_id', $proyectoB->id)
            ->where('notas', 'Gestión exclusiva B')
            ->exists();
        $this->assertTrue($existeEnB);
    }
}
