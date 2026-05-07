<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Cx;

use App\Modules\Personas\Infrastructure\Persistence\Models\PersonaModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Aislamiento entre proyectos cobranza/cx del mismo mandante (§2.1 CLAUDE.md).
 */
final class MultiTenancyCobranzaCxTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_persona_con_misma_cedula_aislada_entre_proyectos(): void
    {
        $mandante = $this->crearMandante();
        $proyectoCob = $this->crearProyectoCobranza($mandante);
        $proyectoCx = $this->crearProyectoCx($mandante);
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');

        $cedula = '1102030405';
        DB::table('personas')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoCob->id,
            'tipo_persona' => 'fisica',
            'tipo_identificacion_id' => $tipoCed,
            'identificacion' => $cedula,
            'nombres' => 'Juan',
            'apellidos' => 'Cobranza',
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
        DB::table('personas')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoCx->id,
            'tipo_persona' => 'fisica',
            'tipo_identificacion_id' => $tipoCed,
            'identificacion' => $cedula,
            'nombres' => 'Juan',
            'apellidos' => 'CX',
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        $this->activarProyecto($proyectoCob);
        $countCob = PersonaModel::query()->where('identificacion', $cedula)->count();
        $this->assertSame(1, $countCob);

        $this->activarProyecto($proyectoCx);
        $countCx = PersonaModel::query()->where('identificacion', $cedula)->count();
        $this->assertSame(1, $countCx);

        $totalSinScope = PersonaModel::query()
            ->sinScopeProyecto()
            ->where('identificacion', $cedula)->count();
        $this->assertSame(2, $totalSinScope);
    }
}
