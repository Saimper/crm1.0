<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auditoria;

use App\Modules\Auditoria\Infrastructure\Http\Livewire\ListadoAuditoria;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class MultiTenancyAuditoriaTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_listado_no_muestra_auditorias_de_otro_proyecto(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCx();

        $auditor = $this->crearAuditor($proyectoA);

        DB::table('auditorias')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoA->id,
            'usuario_id' => $auditor->id,
            'entidad_tipo' => 'CasoF34C',
            'entidad_id' => 9991,
            'evento' => 'creado',
            'creada_en' => Carbon::now(),
        ]);
        DB::table('auditorias')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoB->id,
            'usuario_id' => $auditor->id,
            'entidad_tipo' => 'CasoF34C',
            'entidad_id' => 9992,
            'evento' => 'creado',
            'creada_en' => Carbon::now(),
        ]);

        $this->activarProyecto($proyectoA);
        $this->actingAs($auditor);

        $c = Livewire::test(ListadoAuditoria::class);
        $registros = $c->viewData('registros');

        $idsB = DB::table('auditorias')->where('proyecto_id', $proyectoB->id)->pluck('id')->all();
        foreach ($registros as $r) {
            $this->assertNotContains($r->id, $idsB);
        }
    }
}
