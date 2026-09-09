<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Compromisos;

use App\Modules\Compromisos\Infrastructure\Http\Livewire\ListadoCompromisos;
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
 * F34B — listado paginado de compromisos por proyecto + multi-tenancy.
 */
final class ListadoCompromisosTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_ve_resumen_y_listado(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);

        $this->crearCompromisoEn($proyecto, 'pendiente');
        $this->crearCompromisoEn($proyecto, 'cumplido');
        $this->crearCompromisoEn($proyecto, 'roto');

        $this->activarProyecto($proyecto);
        $this->actingAs($supervisor);

        $totalDb = (int) DB::table('compromisos')
            ->where('proyecto_id', $proyecto->id)
            ->whereNull('eliminada_en')
            ->count();

        $this->assertSame(3, $totalDb);

        $c = Livewire::test(ListadoCompromisos::class);
        $compromisos = $c->viewData('compromisos');
        $this->assertSame($totalDb, $compromisos->total());

        $resumen = $c->viewData('resumen');
        $this->assertSame(1, $resumen['pendientes']);
        $this->assertSame(1, $resumen['cumplidos']);
        $this->assertSame(1, $resumen['rotos']);
    }

    public function test_filtro_estado_pendiente(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);

        $this->crearCompromisoEn($proyecto, 'pendiente');
        $this->crearCompromisoEn($proyecto, 'pendiente');
        $this->crearCompromisoEn($proyecto, 'cumplido');
        $this->crearCompromisoEn($proyecto, 'cancelado');

        $this->activarProyecto($proyecto);
        $this->actingAs($supervisor);

        $countPendientes = (int) DB::table('compromisos')
            ->where('proyecto_id', $proyecto->id)
            ->where('estado', 'pendiente')
            ->whereNull('eliminada_en')
            ->count();

        $this->assertSame(2, $countPendientes);

        $c = Livewire::test(ListadoCompromisos::class)->set('estado', 'pendiente');
        $this->assertSame($countPendientes, $c->viewData('compromisos')->total());
    }

    public function test_no_filtra_compromisos_de_otro_proyecto(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCx();

        $this->crearCompromisoEn($proyectoA, 'pendiente');
        $this->crearCompromisoEn($proyectoA, 'cumplido');
        $this->crearCompromisoEn($proyectoB, 'pendiente', 'resolucion_ticket');
        $this->crearCompromisoEn($proyectoB, 'roto', 'resolucion_ticket');

        $supervisor = $this->crearSupervisor($proyectoA);
        $this->activarProyecto($proyectoA);
        $this->actingAs($supervisor);

        $totalA = (int) DB::table('compromisos')
            ->where('proyecto_id', $proyectoA->id)
            ->whereNull('eliminada_en')
            ->count();

        $this->assertSame(2, $totalA);

        $c = Livewire::test(ListadoCompromisos::class);
        $this->assertSame($totalA, $c->viewData('compromisos')->total());

        $idsB = DB::table('compromisos')->where('proyecto_id', $proyectoB->id)->pluck('id')->all();
        $this->assertCount(2, $idsB);

        foreach ($c->viewData('compromisos') as $comp) {
            $this->assertNotContains($comp->id, $idsB);
        }
    }

    public function test_gestor_accede_pantalla(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        $this->actingAs($gestor)
            ->get(route('proyectos.compromisos.lista', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(200);
    }

    /**
     * Un compromiso del proyecto con su caso propio. Se inserta directo porque
     * `EscenarioOperativo` no expone un helper de compromisos suelto (el de
     * `InsertaCti` exige tipo de pago y sólo hace promesas de pago).
     */
    private function crearCompromisoEn(
        stdClass $proyecto,
        string $estado = 'pendiente',
        string $tipoCompromiso = 'promesa_pago',
    ): int {
        $casoId = $this->crearCasoEn($proyecto);
        $usuario = $this->crearGestor($proyecto);
        $ahora = Carbon::now();

        return (int) DB::table('compromisos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'caso_id' => $casoId,
            'tipo_compromiso' => $tipoCompromiso,
            'estado' => $estado,
            'fecha_vencimiento' => Carbon::today()->addWeek()->toDateString(),
            'fecha_resolucion' => $estado === 'pendiente' ? null : Carbon::today()->toDateString(),
            'usuario_id' => $usuario->id,
            'creada_en' => $ahora,
            'actualizada_en' => $ahora,
        ]);
    }
}
