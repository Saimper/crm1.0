<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Modules\Tenancy\Domain\ConfiguracionProyecto\CalculadorAvanceConfiguracion;
use App\Modules\Tenancy\Domain\ConfiguracionProyecto\PasoConfiguracion;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\PasoCarteras;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\PasoTiposGestion;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Multi-tenancy end-to-end del wizard F36 (§13.5 CLAUDE.md).
 * Verifica el aislamiento estricto de datos por proyecto_id en los flujos
 * de configuración: nada que se cree en el proyecto X aparece, choca, o
 * se ve afectado al operar en el proyecto Y.
 */
final class ConfiguradorMultiTenancyTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_configurar_un_proyecto_no_filtra_datos_a_otro_proyecto(): void
    {
        $mandante = $this->crearMandante();
        $proyectoX = $this->crearProyecto('cobranza', $mandante, 'PRY_X');
        $proyectoY = $this->crearProyecto('cobranza', $mandante, 'PRY_Y');

        $modeloX = ProyectoModel::query()->findOrFail($proyectoX->id);
        $modeloY = ProyectoModel::query()->findOrFail($proyectoY->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(PasoCarteras::class, ['proyecto' => $modeloX])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'OPERACION_A')
            ->set('form.nombre', 'Operación A')
            ->call('guardarCartera')
            ->assertHasNoErrors();

        // Render del PasoCarteras del proyecto Y NO debe ver la cartera del X.
        $componenteY = Livewire::test(PasoCarteras::class, ['proyecto' => $modeloY]);
        $componenteY->assertSet('proyecto.id', $proyectoY->id);

        // Verificación directa via DB: la cartera no se replicó.
        $this->assertSame(
            1,
            (int) DB::table('carteras')->where('codigo', 'OPERACION_A')->count(),
        );
        $this->assertSame(
            $proyectoX->id,
            (int) DB::table('carteras')->where('codigo', 'OPERACION_A')->value('proyecto_id'),
        );
        $this->assertSame(
            0,
            (int) DB::table('carteras')->where('proyecto_id', $proyectoY->id)->count(),
        );
    }

    public function test_codigos_iguales_en_proyectos_distintos_no_chocan(): void
    {
        $mandante = $this->crearMandante();
        $proyectoX = $this->crearProyecto('cobranza', $mandante, 'PRY_X');
        $proyectoY = $this->crearProyecto('cx', $mandante, 'PRY_Y');

        $modeloX = ProyectoModel::query()->findOrFail($proyectoX->id);
        $modeloY = ProyectoModel::query()->findOrFail($proyectoY->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(PasoTiposGestion::class, ['proyecto' => $modeloX])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'F001')
            ->set('form.nombre', 'Llamada en X')
            ->call('guardar')
            ->assertHasNoErrors();

        Livewire::test(PasoTiposGestion::class, ['proyecto' => $modeloY])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'F001')
            ->set('form.nombre', 'Llamada en Y')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(2, (int) DB::table('tipos_gestion')->where('codigo', 'F001')->count());
        $this->assertDatabaseHas('tipos_gestion', ['proyecto_id' => $proyectoX->id, 'codigo' => 'F001', 'nombre' => 'Llamada en X']);
        $this->assertDatabaseHas('tipos_gestion', ['proyecto_id' => $proyectoY->id, 'codigo' => 'F001', 'nombre' => 'Llamada en Y']);
    }

    public function test_eliminar_cartera_en_proyecto_x_no_afecta_proyecto_y(): void
    {
        $mandante = $this->crearMandante();
        $proyectoX = $this->crearProyecto('cobranza', $mandante, 'PRY_X');
        $proyectoY = $this->crearProyecto('cobranza', $mandante, 'PRY_Y');

        $carteraX = $this->crearCarteraEn($proyectoX, 'CART_COMUN');
        $carteraY = $this->crearCarteraEn($proyectoY, 'CART_COMUN');

        $modeloX = ProyectoModel::query()->findOrFail($proyectoX->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(PasoCarteras::class, ['proyecto' => $modeloX])
            ->call('eliminarCartera', $carteraX->id);

        // Cartera X eliminada (soft).
        $this->assertNotNull(
            DB::table('carteras')->where('id', $carteraX->id)->value('eliminada_en'),
        );
        // Cartera Y intacta.
        $this->assertNull(
            DB::table('carteras')->where('id', $carteraY->id)->value('eliminada_en'),
        );
    }

    public function test_verificadores_devuelven_resultados_independientes_por_proyecto(): void
    {
        $mandante = $this->crearMandante();
        $proyectoCompleto = $this->crearProyecto('cobranza', $mandante, 'COMPLETO');
        $proyectoVacio = $this->crearProyecto('cobranza', $mandante, 'VACIO');

        $this->completarTodosLosObligatoriosCobranza($proyectoCompleto->id);

        /** @var CalculadorAvanceConfiguracion $calculador */
        $calculador = app(CalculadorAvanceConfiguracion::class);

        $avanceCompleto = $calculador->calcular((int) $proyectoCompleto->id);
        $avanceVacio = $calculador->calcular((int) $proyectoVacio->id);

        $this->assertTrue($avanceCompleto->estaCompleto());
        $this->assertFalse($avanceVacio->estaCompleto());
        $this->assertTrue($avanceCompleto->estaCompletado(PasoConfiguracion::CARTERAS));
        $this->assertFalse($avanceVacio->estaCompletado(PasoConfiguracion::CARTERAS));
        $this->assertTrue($avanceCompleto->estaCompletado(PasoConfiguracion::CATALOGOS_TIPO));
        $this->assertFalse($avanceVacio->estaCompletado(PasoConfiguracion::CATALOGOS_TIPO));
    }

    private function completarTodosLosObligatoriosCobranza(int $proyectoId): void
    {
        $now = Carbon::now();

        $this->crearCarteraEn((object) ['id' => $proyectoId]);
        DB::table('estados_caso')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'EST', 'nombre' => 'Estado',
            'orden' => 100, 'activo' => true, 'es_terminal' => false,
            'creada_en' => $now, 'actualizada_en' => $now,
        ]);
        DB::table('tipos_gestion')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'TG', 'nombre' => 'Tipo',
            'orden' => 100, 'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
        DB::table('resultados')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'R', 'nombre' => 'Resultado',
            'orden' => 100, 'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
        DB::table('motivos_no_contacto')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'M', 'nombre' => 'Motivo',
            'orden' => 100, 'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
        DB::table('tramos_mora')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'TM', 'nombre' => 'Tramo',
            'dias_desde' => 0, 'orden' => 100, 'activo' => true,
            'creada_en' => $now, 'actualizada_en' => $now,
        ]);
        DB::table('tipos_pago')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'TP', 'nombre' => 'Tipo pago',
            'orden' => 100, 'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
    }
}
