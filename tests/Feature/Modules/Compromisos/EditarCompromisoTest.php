<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Compromisos;

use App\Models\User;
use App\Modules\Compromisos\Infrastructure\Http\Livewire\EditarCompromiso;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * F34B — edición de compromiso solo si pendiente. Sin tocar Domain del núcleo.
 */
final class EditarCompromisoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_edita_promesa_pago_pendiente(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);
        $this->activarProyecto($proyecto);
        $this->actingAs($supervisor);

        $compromiso = $this->compromisoPendiente($proyecto, 'promesa_pago', $supervisor);

        Livewire::test(EditarCompromiso::class, ['compromiso' => $compromiso->public_id])
            ->set('monto', '12345.67')
            ->set('fechaVencimiento', Carbon::today()->addDays(15)->format('Y-m-d'))
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('12345.67',
            (string) DB::table('compromisos_promesa_pago')->where('compromiso_id', $compromiso->id)->value('monto')
        );
    }

    public function test_compromiso_no_pendiente_no_se_carga(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);
        $this->activarProyecto($proyecto);
        $this->actingAs($supervisor);

        $compromiso = $this->compromisoPendiente($proyecto, 'promesa_pago', $supervisor);

        // Marca como cumplido vía DB directo (no usamos resolver use case para
        // simplificar el test; el editor solo decide por estado actual).
        DB::table('compromisos')->where('id', $compromiso->id)->update([
            'estado' => 'cumplido',
            'fecha_resolucion' => Carbon::today()->toDateString(),
        ]);

        // El componente aborta con 409; Livewire lo traduce a respuesta HTTP.
        Livewire::test(EditarCompromiso::class, ['compromiso' => $compromiso->public_id])
            ->assertStatus(409);
    }

    public function test_compromiso_de_otro_proyecto_no_se_carga(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCx();

        $supervisor = $this->crearSupervisor($proyectoA);
        $this->activarProyecto($proyectoA);
        $this->actingAs($supervisor);

        $compromisoB = $this->compromisoPendiente($proyectoB, 'resolucion_ticket', $supervisor);

        // El compromiso existe, pero no en el proyecto activo: 404, no fuga.
        Livewire::test(EditarCompromiso::class, ['compromiso' => $compromisoB->public_id])
            ->assertStatus(404);
    }

    public function test_auditor_recibe_403_en_ruta(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $auditor = $this->crearAuditor($proyecto);
        $compromiso = $this->compromisoPendiente($proyecto, 'promesa_pago', $auditor);

        $this->actingAs($auditor)
            ->get(route('proyectos.compromisos.editar', [
                'proyecto_id' => $proyecto->id,
                'compromiso' => $compromiso->public_id,
            ]))
            ->assertStatus(403);
    }

    /**
     * Un compromiso pendiente del tipo pedido, con su fila CTI. Antes venía del
     * seeder demo; ahora se monta aquí porque el trait no cubre compromisos.
     */
    private function compromisoPendiente(stdClass $proyecto, string $tipo, User $usuario): object
    {
        $casoId = $this->crearCasoEn($proyecto);
        $ahora = Carbon::now();

        $compromisoId = (int) DB::table('compromisos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'caso_id' => $casoId,
            'tipo_compromiso' => $tipo,
            'estado' => 'pendiente',
            'fecha_vencimiento' => Carbon::today()->addDays(7)->toDateString(),
            'usuario_id' => $usuario->id,
            'creada_en' => $ahora,
            'actualizada_en' => $ahora,
        ]);

        if ($tipo === 'promesa_pago') {
            DB::table('compromisos_promesa_pago')->insert([
                'compromiso_id' => $compromisoId,
                'proyecto_id' => $proyecto->id,
                'monto' => '500.00',
                'moneda' => 'USD',
                'creada_en' => $ahora,
                'actualizada_en' => $ahora,
            ]);
        } elseif ($tipo === 'resolucion_ticket') {
            DB::table('compromisos_resolucion_ticket')->insert([
                'compromiso_id' => $compromisoId,
                'proyecto_id' => $proyecto->id,
                'accion_comprometida' => 'Acción mínima',
                'fecha_limite_sla' => Carbon::now()->addDays(7),
                'creada_en' => $ahora,
                'actualizada_en' => $ahora,
            ]);
        }

        return (object) DB::table('compromisos')->where('id', $compromisoId)->first();
    }
}
