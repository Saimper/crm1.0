<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use App\Modules\Reportes\Infrastructure\Http\Livewire\ListadoReportesCustom;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class MultiTenancyReportesTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_listado_definiciones_filtra_por_proyecto_activo(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCx();

        $supervisorA = $this->crearSupervisor($proyectoA);

        $base = [
            'entidad_raiz' => 'casos',
            'columnas' => json_encode([]),
            'filtros' => json_encode([]),
            'agrupaciones' => json_encode([]),
            'orden' => json_encode([]),
            'creado_por_usuario_id' => $supervisorA->id,
        ];
        DB::table('reportes_definiciones')->insert(array_merge($base, [
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoA->id,
            'codigo' => 'REP_A',
            'nombre' => 'Reporte A',
        ]));
        DB::table('reportes_definiciones')->insert(array_merge($base, [
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoB->id,
            'codigo' => 'REP_B',
            'nombre' => 'Reporte B',
        ]));

        $this->activarProyecto($proyectoA);
        $this->actingAs($supervisorA);

        $c = Livewire::test(ListadoReportesCustom::class);
        $defs = $c->viewData('definiciones');
        $codigos = collect($defs)->pluck('codigo')->all();
        $this->assertContains('REP_A', $codigos);
        $this->assertNotContains('REP_B', $codigos);
    }
}
