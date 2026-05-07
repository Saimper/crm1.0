<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use App\Modules\Importaciones\Infrastructure\Http\Livewire\ImportarPersonas;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class MultiTenancyImportacionesTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_historial_no_muestra_imports_de_otro_proyecto(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCx();

        $supervisor = $this->crearSupervisor($proyectoA);

        DB::table('importaciones')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoB->id,
            'tipo_entidad' => 'persona',
            'modo' => 'merge',
            'estado' => 'completada',
            'usuario_id' => $supervisor->id,
            'nombre_archivo' => 'foreign.csv',
            'total_filas' => 1,
        ]);
        DB::table('importaciones')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoA->id,
            'tipo_entidad' => 'persona',
            'modo' => 'merge',
            'estado' => 'completada',
            'usuario_id' => $supervisor->id,
            'nombre_archivo' => 'mio.csv',
            'total_filas' => 1,
        ]);

        $this->activarProyecto($proyectoA);
        $this->actingAs($supervisor);

        $c = Livewire::test(ImportarPersonas::class);
        $historial = $c->viewData('historial');

        $codigosVistos = collect($historial)->pluck('nombre_archivo')->all();
        $this->assertContains('mio.csv', $codigosVistos);
        $this->assertNotContains('foreign.csv', $codigosVistos);
    }
}
