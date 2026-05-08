<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy\ConfiguradorPasos;

use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\PasoCarteras;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class PasoCarterasTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_crea_cartera_con_codigo_unico_por_proyecto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        Livewire::test(PasoCarteras::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'NUEVA_CARTERA')
            ->set('form.nombre', 'Nueva cartera')
            ->call('guardarCartera')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('carteras', [
            'proyecto_id' => $proyecto->id,
            'codigo' => 'NUEVA_CARTERA',
            'nombre' => 'Nueva cartera',
            'activo' => true,
        ]);
    }

    public function test_codigo_duplicado_en_mismo_proyecto_falla(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->crearCarteraEn($proyecto, 'YA_EXISTE');
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);

        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        Livewire::test(PasoCarteras::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'YA_EXISTE')
            ->set('form.nombre', 'Duplicada')
            ->call('guardarCartera')
            ->assertHasErrors(['form.codigo']);

        $this->assertSame(
            1,
            (int) DB::table('carteras')
                ->where('proyecto_id', $proyecto->id)
                ->where('codigo', 'YA_EXISTE')
                ->count(),
        );
    }

    public function test_codigo_duplicado_en_otro_proyecto_es_permitido(): void
    {
        $mandante = $this->crearMandante();
        $proyectoA = $this->crearProyecto('cobranza', $mandante);
        $proyectoB = $this->crearProyecto('cx', $mandante);

        $this->crearCarteraEn($proyectoA, 'COMUN');

        $modeloB = ProyectoModel::query()->findOrFail($proyectoB->id);
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        Livewire::test(PasoCarteras::class, ['proyecto' => $modeloB])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'COMUN')
            ->set('form.nombre', 'Cartera proyecto B')
            ->call('guardarCartera')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('carteras', [
            'proyecto_id' => $proyectoB->id,
            'codigo' => 'COMUN',
        ]);
        $this->assertDatabaseHas('carteras', [
            'proyecto_id' => $proyectoA->id,
            'codigo' => 'COMUN',
        ]);
    }

    public function test_no_elimina_cartera_con_casos(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto, 'ABIERTO');

        DB::table('casos')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'cartera_id' => $cartera->id,
            'persona_id' => $persona->id,
            'tipo_caso' => 'cobranza',
            'estado_caso_id' => $estado->id,
            'fecha_ingreso' => Carbon::today(),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        Livewire::test(PasoCarteras::class, ['proyecto' => $modelo])
            ->call('eliminarCartera', $cartera->id);

        $this->assertNull(
            DB::table('carteras')->where('id', $cartera->id)->value('eliminada_en'),
            'La cartera no debe quedar marcada como eliminada cuando tiene casos asociados.',
        );
    }

    public function test_emite_evento_paso_completado_al_crear_primera_cartera(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        Livewire::test(PasoCarteras::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'PRIMERA')
            ->set('form.nombre', 'Primera cartera')
            ->call('guardarCartera')
            ->assertHasNoErrors()
            ->assertDispatched('configuracion-paso-completado');
    }

    public function test_supervisor_no_accede_al_wizard(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);

        $this->actingAs($supervisor)
            ->get(route('admin.proyectos.configurar', ['proyecto' => $proyecto->public_id]))
            ->assertStatus(403);
    }
}
