<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy\ConfiguradorPasos;

use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\PasoEstadosCaso;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class PasoEstadosCasoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_crea_estado_con_codigo_unico_por_proyecto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        Livewire::test(PasoEstadosCaso::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'ABIERTO')
            ->set('form.nombre', 'Abierto')
            ->set('form.orden', 1)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('estados_caso', [
            'proyecto_id' => $proyecto->id,
            'codigo' => 'ABIERTO',
            'nombre' => 'Abierto',
            'es_terminal' => false,
            'activo' => true,
        ]);
    }

    public function test_codigo_duplicado_en_otro_proyecto_permitido(): void
    {
        $mandante = $this->crearMandante();
        $proyectoA = $this->crearProyecto('cobranza', $mandante);
        $proyectoB = $this->crearProyecto('cx', $mandante);

        $this->crearEstadoCasoEn($proyectoA, 'COMUN');

        $modeloB = ProyectoModel::query()->findOrFail($proyectoB->id);
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        Livewire::test(PasoEstadosCaso::class, ['proyecto' => $modeloB])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'COMUN')
            ->set('form.nombre', 'Estado común')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('estados_caso', ['proyecto_id' => $proyectoA->id, 'codigo' => 'COMUN']);
        $this->assertDatabaseHas('estados_caso', ['proyecto_id' => $proyectoB->id, 'codigo' => 'COMUN']);
    }

    public function test_bloquea_eliminacion_si_hay_casos_en_ese_estado(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto, 'EN_USO');

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

        Livewire::test(PasoEstadosCaso::class, ['proyecto' => $modelo])
            ->call('eliminar', $estado->id);

        $this->assertDatabaseHas('estados_caso', ['id' => $estado->id]);
    }

    public function test_emite_evento_paso_completado_al_crear_primer_estado(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        Livewire::test(PasoEstadosCaso::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'PRIMERO')
            ->set('form.nombre', 'Primero')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('configuracion-paso-completado');
    }
}
