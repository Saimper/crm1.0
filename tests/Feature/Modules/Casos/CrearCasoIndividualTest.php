<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Casos;

use App\Modules\Casos\Infrastructure\Http\Livewire\CrearCasoIndividual;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class CrearCasoIndividualTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_crea_caso_cobranza_asigna_primer_estado_automaticamente(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);

        $estadoPrimero = DB::table('estados_caso')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'ABIERTO',
            'nombre' => 'Abierto',
            'activo' => true,
            'es_terminal' => false,
            'orden' => 1,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
        DB::table('estados_caso')->insert([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'EN_GESTION',
            'nombre' => 'En gestión',
            'activo' => true,
            'es_terminal' => false,
            'orden' => 2,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        $this->actuarComoSupervisor($proyecto);

        Livewire::test(CrearCasoIndividual::class, ['personaPublicId' => $persona->public_id])
            ->set('carteraId', (string) $cartera->id)
            ->set('fechaIngreso', '2026-04-30')
            ->set('numeroPrestamo', 'CCI-PRST-0001')
            ->set('moneda', 'USD')
            ->set('montoOriginal', '5000.00')
            ->set('saldoCapital', '4000.00')
            ->set('saldoInteres', '100.00')
            ->set('saldoTotal', '4100.00')
            ->set('cuotaMensual', '450.00')
            ->set('cuotasTotales', 12)
            ->set('cuotasPagadas', 1)
            ->set('diasMora', 0)
            ->set('fechaDesembolso', '2026-01-01')
            ->set('fechaVencimiento', '2027-01-01')
            ->call('guardar')
            ->assertHasNoErrors();

        $caso = DB::table('casos')
            ->where('proyecto_id', $proyecto->id)
            ->where('persona_id', $persona->id)
            ->first();
        $this->assertNotNull($caso);
        $this->assertSame((int) $estadoPrimero, (int) $caso->estado_caso_id);
    }

    public function test_crea_caso_cx_asigna_primer_estado_automaticamente(): void
    {
        $proyecto = $this->crearProyectoCx();
        $cartera = $this->crearCarteraEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);

        $estadoPrimero = DB::table('estados_caso')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'ABIERTO',
            'nombre' => 'Abierto',
            'activo' => true,
            'es_terminal' => false,
            'orden' => 1,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        $this->actuarComoSupervisor($proyecto);

        Livewire::test(CrearCasoIndividual::class, ['personaPublicId' => $persona->public_id])
            ->set('carteraId', (string) $cartera->id)
            ->set('fechaIngreso', '2026-04-30')
            ->set('codigoTicket', 'CCI-TKT-0001')
            ->set('asunto', 'Reclamo')
            ->set('descripcion', 'Detalle.')
            ->set('fechaReporte', '2026-04-30T10:00')
            ->call('guardar')
            ->assertHasNoErrors();

        $caso = DB::table('casos')
            ->where('proyecto_id', $proyecto->id)
            ->where('persona_id', $persona->id)
            ->first();
        $this->assertNotNull($caso);
        $this->assertSame((int) $estadoPrimero, (int) $caso->estado_caso_id);
    }

    public function test_falla_si_proyecto_sin_estados_activos(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);

        $this->actuarComoSupervisor($proyecto);

        Livewire::test(CrearCasoIndividual::class, ['personaPublicId' => $persona->public_id])
            ->set('carteraId', (string) $cartera->id)
            ->set('fechaIngreso', '2026-04-30')
            ->set('numeroPrestamo', 'X')
            ->set('moneda', 'USD')
            ->set('montoOriginal', '1')
            ->set('saldoCapital', '1')
            ->set('saldoInteres', '0')
            ->set('saldoTotal', '1')
            ->set('cuotaMensual', '1')
            ->set('cuotasTotales', 1)
            ->set('fechaDesembolso', '2026-01-01')
            ->set('fechaVencimiento', '2027-01-01')
            ->call('guardar')
            ->assertHasErrors(['general']);

        $this->assertSame(
            0,
            (int) DB::table('casos')->where('proyecto_id', $proyecto->id)->count()
        );
    }

    public function test_form_no_renderiza_select_estado_inicial(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $persona = $this->crearPersonaEn($proyecto);
        $this->crearEstadoCasoEn($proyecto, 'ABIERTO');

        $this->actuarComoSupervisor($proyecto);

        $resp = $this->get(route('proyectos.casos.crear', [
            'proyecto_id' => $proyecto->id,
            'persona' => $persona->public_id,
        ]));

        $resp->assertOk();
        $resp->assertDontSee('Estado inicial');
        $resp->assertDontSee('wire:model="estadoCasoId"', false);
    }

    public function test_persona_invalida_no_crea_caso(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->crearEstadoCasoEn($proyecto, 'ABIERTO');
        $this->actuarComoSupervisor($proyecto);

        $ulidFalso = (string) Str::ulid();

        Livewire::test(CrearCasoIndividual::class, ['personaPublicId' => $ulidFalso])
            ->call('guardar')
            ->assertHasErrors();
    }

    public function test_gestor_recibe_403_en_ruta(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        $this->actingAs($gestor)
            ->get(route('proyectos.casos.crear', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(403);
    }

    public function test_supervisor_accede_ruta(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->crearEstadoCasoEn($proyecto, 'ABIERTO');
        $supervisor = $this->crearSupervisor($proyecto);

        $this->actingAs($supervisor)
            ->get(route('proyectos.casos.crear', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(200);
    }

    private function actuarComoSupervisor(stdClass $proyecto): void
    {
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));
    }
}
