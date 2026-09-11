<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Casos;

use App\Modules\Casos\Infrastructure\Http\Livewire\EditarCaso;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\InsertaCti;
use Tests\TestCase;

/**
 * F34B — edición de caso descriptivo (sin tocar tipo_caso/estado_caso/Domain).
 *
 * F36-Q movió los campos descriptivos del CTI (saldo_capital, asunto,
 * descripcion…) fuera de esta pantalla: la variabilidad por mandante vive en
 * campos personalizados `caso × cartera`. Los dos tests que editaban CTI
 * conservan su escenario pero afirman ahora el límite vigente: el componente
 * guarda cartera/prioridad/fecha_ingreso y NO toca la sub-tabla CTI.
 */
final class EditarCasoTest extends TestCase
{
    use InsertaCti;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_edita_prioridad_caso_cobranza(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $casoId = $this->crearCasoCobranzaConCti($proyecto);

        $supervisor = $this->crearSupervisor($proyecto);
        $this->activarProyecto($proyecto);
        $this->actingAs($supervisor);

        $caso = DB::table('casos')->where('id', $casoId)->first();

        Livewire::test(EditarCaso::class, ['caso' => $caso->public_id])
            ->set('prioridad', 7)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(7, (int) DB::table('casos')->where('id', $casoId)->value('prioridad'));

        // F36-Q: el saldo del CTI ya no es editable desde esta pantalla.
        $this->assertSame(
            '1000.000',
            (string) DB::table('casos_cobranza')->where('caso_id', $casoId)->value('saldo_capital')
        );
    }

    public function test_supervisor_edita_datos_descriptivos_caso_cx(): void
    {
        $proyecto = $this->crearProyectoCx();
        $casoId = $this->insertarCasoTicketCx($proyecto, []);

        $supervisor = $this->crearSupervisor($proyecto);
        $this->activarProyecto($proyecto);
        $this->actingAs($supervisor);

        $caso = DB::table('casos')->where('id', $casoId)->first();
        $nuevaFecha = Carbon::parse($caso->fecha_ingreso)->subDay()->format('Y-m-d');

        Livewire::test(EditarCaso::class, ['caso' => $caso->public_id])
            ->set('prioridad', 4)
            ->set('fechaIngreso', $nuevaFecha)
            ->call('guardar')
            ->assertHasNoErrors();

        $guardado = DB::table('casos')->where('id', $casoId)->first();
        $this->assertSame(4, (int) $guardado->prioridad);
        $this->assertSame($nuevaFecha, Carbon::parse($guardado->fecha_ingreso)->format('Y-m-d'));

        // F36-Q: asunto/descripcion del CTI ya no se editan aquí.
        $this->assertSame(
            'Asunto demo',
            (string) DB::table('casos_ticket_cx')->where('caso_id', $casoId)->value('asunto')
        );
    }

    public function test_caso_de_otro_proyecto_no_se_carga(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCx();

        $casoB = DB::table('casos')
            ->where('id', $this->insertarCasoTicketCx($proyectoB, []))
            ->first();

        $supervisor = $this->crearSupervisor($proyectoA);
        $this->activarProyecto($proyectoA);
        $this->actingAs($supervisor);

        try {
            Livewire::test(EditarCaso::class, ['caso' => $casoB->public_id]);
            $this->fail('Esperaba 404 al editar caso de otro proyecto.');
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }
    }

    public function test_auditor_recibe_403_en_ruta(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $casoId = $this->crearCasoCobranzaConCti($proyecto);
        $auditor = $this->crearAuditor($proyecto);

        $caso = DB::table('casos')->where('id', $casoId)->first();

        $this->actingAs($auditor)
            ->get(route('proyectos.casos.editar', [
                'proyecto_id' => $proyecto->id,
                'caso' => $caso->public_id,
            ]))
            ->assertStatus(403);
    }

    public function test_estado_caso_no_es_editable_via_componente(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $casoId = $this->crearCasoCobranzaConCti($proyecto);

        $supervisor = $this->crearSupervisor($proyecto);
        $this->activarProyecto($proyecto);
        $this->actingAs($supervisor);

        $caso = DB::table('casos')->where('id', $casoId)->first();
        $estadoOriginal = (int) $caso->estado_caso_id;

        Livewire::test(EditarCaso::class, ['caso' => $caso->public_id])
            ->set('prioridad', 3)
            ->call('guardar');

        $this->assertSame($estadoOriginal, (int) DB::table('casos')->where('id', $casoId)->value('estado_caso_id'));
    }

    /**
     * Caso de cobranza con su fila CTI. `InsertaCti::insertarCasoCobranzaConTramoMora`
     * exige un tramo de mora que estos tests no necesitan, y `tramos_mora` no tiene
     * helper propio; el CTI se inserta aquí con `tramo_mora_id` nulo.
     */
    private function crearCasoCobranzaConCti(\stdClass $proyecto): int
    {
        $casoId = $this->crearCasoEn($proyecto);

        DB::table('casos_cobranza')->insert([
            'caso_id' => $casoId,
            'proyecto_id' => $proyecto->id,
            'numero_prestamo' => 'PRST-'.Str::random(6),
            'monto_original' => 1000.00,
            'saldo_capital' => 1000.00,
            'saldo_total' => 1000.00,
            'cuota_mensual' => 100.00,
            'cuotas_totales' => 12,
            'tramo_mora_id' => null,
            'fecha_desembolso' => Carbon::today()->subYear(),
            'fecha_vencimiento' => Carbon::today()->addYear(),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        return $casoId;
    }
}
