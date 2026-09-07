<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Campanas;

use App\Modules\Campanas\Infrastructure\Http\Livewire\CampanasProyecto;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Support\EscenarioMultiMandante;
use Tests\TestCase;

/**
 * Sin campañas no se puede asignar nada: `asignaciones.campana_id` es NOT NULL.
 * Y sin asignación, la bandeja de todo gestor queda vacía para siempre. El
 * módulo tenía dominio y caso de uso desde el principio, pero ninguna pantalla
 * ni ruta, así que la cadena estaba rota desde el primer eslabón.
 */
final class CampanasProyectoTest extends TestCase
{
    use EscenarioMultiMandante;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** @return array{0: array<string,mixed>, 1: array<string,mixed>} */
    private function escenario(): array
    {
        $m = $this->montarDosMandantes();
        app()->instance('tenancy.proyecto_activo', (object) ['id' => (int) $m['a']['proyecto']->id]);

        return [$m['a'], $m['b']];
    }

    public function test_un_supervisor_puede_crear_una_campana(): void
    {
        [$a] = $this->escenario();

        Livewire::actingAs($a['supervisor'])->test(CampanasProyecto::class)
            ->call('abrirFormCrear')
            ->set('form.codigo', 'SEP26')
            ->set('form.nombre', 'Cobranza septiembre')
            ->set('form.fecha_inicio', '2026-09-01')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('campanas', [
            'proyecto_id' => (int) $a['proyecto']->id,
            'codigo' => 'SEP26',
        ]);
    }

    public function test_la_campana_nace_en_el_proyecto_activo_y_no_en_otro(): void
    {
        [$a, $b] = $this->escenario();

        Livewire::actingAs($a['supervisor'])->test(CampanasProyecto::class)
            ->call('abrirFormCrear')
            ->set('form.codigo', 'SEP26')
            ->set('form.nombre', 'Cobranza septiembre')
            ->set('form.fecha_inicio', '2026-09-01')
            ->call('guardar');

        $this->assertSame(0, DB::table('campanas')->where('proyecto_id', $b['proyecto']->id)->count());
    }

    public function test_el_id_del_proyecto_no_se_puede_fijar_desde_el_cliente(): void
    {
        [$a, $b] = $this->escenario();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($a['supervisor'])->test(CampanasProyecto::class)
            ->set('proyectoId', (int) $b['proyecto']->id);
    }

    public function test_un_gestor_no_puede_crear_campanas(): void
    {
        [$a] = $this->escenario();

        Livewire::actingAs($a['gestor'])->test(CampanasProyecto::class)
            ->call('abrirFormCrear')
            ->assertStatus(403);
    }

    public function test_no_se_puede_cambiar_el_estado_de_una_campana_de_otro_proyecto(): void
    {
        [$a, $b] = $this->escenario();

        $ajena = DB::table('campanas')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => (int) $b['proyecto']->id,
            'codigo' => 'AJENA',
            'nombre' => 'Campaña del otro cliente',
            'estado' => 'programada',
            'fecha_inicio' => '2026-09-01',
            'creada_por_id' => (int) $b['supervisor']->id,
        ]);

        Livewire::actingAs($a['supervisor'])->test(CampanasProyecto::class)
            ->call('cambiarEstado', $ajena, 'activa');

        $this->assertSame(
            'programada',
            DB::table('campanas')->where('id', $ajena)->value('estado'),
            'El id llega del cliente: la escritura tiene que acotarse al proyecto activo.'
        );
    }

    public function test_el_listado_no_muestra_campanas_de_otro_proyecto(): void
    {
        [$a, $b] = $this->escenario();

        DB::table('campanas')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => (int) $b['proyecto']->id,
            'codigo' => 'AJENA_BETA',
            'nombre' => 'Campaña del otro cliente',
            'estado' => 'activa',
            'fecha_inicio' => '2026-09-01',
            'creada_por_id' => (int) $b['supervisor']->id,
        ]);

        $html = Livewire::actingAs($a['supervisor'])->test(CampanasProyecto::class)->html();

        $this->assertStringNotContainsString('AJENA_BETA', $html);
    }

    public function test_avisa_de_cuantas_cuentas_quedan_sin_repartir(): void
    {
        [$a] = $this->escenario();

        // El caso del escenario todavía no está en ninguna campaña.
        Livewire::actingAs($a['supervisor'])->test(CampanasProyecto::class)
            ->assertViewHas('casosSinAsignar', 1);
    }

    public function test_un_codigo_repetido_en_el_mismo_proyecto_se_rechaza(): void
    {
        [$a] = $this->escenario();

        $crear = fn () => Livewire::actingAs($a['supervisor'])->test(CampanasProyecto::class)
            ->call('abrirFormCrear')
            ->set('form.codigo', 'SEP26')
            ->set('form.nombre', 'Cobranza septiembre')
            ->set('form.fecha_inicio', '2026-09-01')
            ->call('guardar');

        $crear();
        $crear()->assertHasErrors('form.codigo');

        $this->assertSame(1, DB::table('campanas')->where('codigo', 'SEP26')->count());
    }
}
