<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy\ConfiguradorPasos;

use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\PasoResumen;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class PasoResumenTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_muestra_conteos_correctos_de_cada_paso(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->completarTodosLosObligatorios($proyecto);
        $this->crearCarteraEn($proyecto, 'CART_2'); // total carteras = 2

        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        $component = Livewire::test(PasoResumen::class, ['proyecto' => $modelo]);
        $conteos = $component->get('conteos');

        $this->assertSame(2, $conteos['carteras']);
        $this->assertSame(1, $conteos['estados_caso']);
        $this->assertSame(1, $conteos['tipos_gestion']);
        $this->assertSame(1, $conteos['resultados']);
        $this->assertSame(1, $conteos['motivos_no_contacto']);
    }

    public function test_oculta_seccion_tipo_si_no_aplica(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        $component = Livewire::test(PasoResumen::class, ['proyecto' => $modelo]);
        /** @var list<array{codigo: string, etiqueta: string, conteo: int}> $catalogos */
        $catalogos = $component->get('catalogosTipo');

        $codigos = array_column($catalogos, 'codigo');

        // Cobranza: solo tramos_mora + tipos_pago.
        $this->assertContains('tramos_mora', $codigos);
        $this->assertContains('tipos_pago', $codigos);
        // No debe mostrar catálogos de cx ni venta ni servicio.
        $this->assertNotContains('categorias_ticket', $codigos);
        $this->assertNotContains('productos_venta', $codigos);
        $this->assertNotContains('estados_tecnicos', $codigos);
    }

    public function test_boton_finalizar_deshabilitado_si_falta_obligatorio(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        $component = Livewire::test(PasoResumen::class, ['proyecto' => $modelo]);

        $component->assertSet('estaCompleto', false);
        $this->assertNotEmpty($component->get('pasosPendientes'));
    }

    public function test_boton_finalizar_habilitado_si_todos_obligatorios_completos(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->completarTodosLosObligatorios($proyecto);
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        $component = Livewire::test(PasoResumen::class, ['proyecto' => $modelo]);

        $component->assertSet('estaCompleto', true);
        $this->assertSame([], $component->get('pasosPendientes'));
    }

    public function test_finalizar_actualiza_configuracion_completada_en(): void
    {
        if (! Schema::hasColumn('proyectos', 'configuracion_completada_en')) {
            $this->markTestSkipped(
                'La columna `proyectos.configuracion_completada_en` no existe en schema. '
                .'El cierre se detecta en runtime via CalculadorAvanceConfiguracion. '
                .'Ver commit message P6.',
            );
        }

        $proyecto = $this->crearProyectoCobranza();
        $this->completarTodosLosObligatorios($proyecto);
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(PasoResumen::class, ['proyecto' => $modelo])->call('finalizar');

        $this->assertNotNull(
            DB::table('proyectos')->where('id', $proyecto->id)->value('configuracion_completada_en'),
        );
    }

    public function test_finalizar_redirige_a_bandeja_del_proyecto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->completarTodosLosObligatorios($proyecto);
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(PasoResumen::class, ['proyecto' => $modelo])
            ->call('finalizar')
            ->assertRedirect(route('proyectos.bandeja', ['proyecto_id' => $proyecto->id]));
    }

    public function test_volver_al_inicio_navega_al_paso_1(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(PasoResumen::class, ['proyecto' => $modelo])
            ->call('volverAlInicio')
            ->assertDispatched('configuracion-ir-a-paso', paso: 'datos_proyecto');
    }

    public function test_finalizar_sin_obligatorios_completos_lanza_validacion(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        $tsAntes = (string) DB::table('proyectos')->where('id', $proyecto->id)->value('actualizada_en');

        Livewire::test(PasoResumen::class, ['proyecto' => $modelo])
            ->call('finalizar')
            ->assertNoRedirect();

        // El UPDATE de actualizada_en NO debe haberse aplicado.
        $tsDespues = (string) DB::table('proyectos')->where('id', $proyecto->id)->value('actualizada_en');
        $this->assertSame($tsAntes, $tsDespues, 'El proyecto no debe actualizarse si faltan obligatorios.');
    }

    private function completarTodosLosObligatorios(stdClass $proyecto): void
    {
        $proyectoId = (int) $proyecto->id;
        $now = Carbon::now();

        $this->crearCarteraEn($proyecto);
        $this->crearEstadoCasoEn($proyecto);

        DB::table('tipos_gestion')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'TG', 'nombre' => 'Tipo', 'orden' => 100,
            'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
        DB::table('resultados')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'R', 'nombre' => 'Resultado', 'orden' => 100,
            'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
        DB::table('motivos_no_contacto')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'M', 'nombre' => 'Motivo', 'orden' => 100,
            'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
        DB::table('tramos_mora')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'TM', 'nombre' => 'Tramo', 'dias_desde' => 0,
            'orden' => 100, 'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
        DB::table('tipos_pago')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'TP', 'nombre' => 'Tipo pago', 'orden' => 100,
            'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
    }
}
