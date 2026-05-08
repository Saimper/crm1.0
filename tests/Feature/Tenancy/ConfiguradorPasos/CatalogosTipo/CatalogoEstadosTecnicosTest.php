<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy\ConfiguradorPasos\CatalogosTipo;

use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\CatalogosTipo\CatalogoEstadosTecnicos;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\InsertaCti;
use Tests\TestCase;

final class CatalogoEstadosTecnicosTest extends TestCase
{
    use InsertaCti;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_crea_registro_con_codigo_unico_por_proyecto(): void
    {
        $proyecto = $this->crearProyectoServicio();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(CatalogoEstadosTecnicos::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'PROGRAMADO')
            ->set('form.nombre', 'Programado')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('estados_tecnicos', [
            'proyecto_id' => $proyecto->id,
            'codigo' => 'PROGRAMADO',
        ]);
    }

    public function test_codigo_duplicado_en_otro_proyecto_permitido(): void
    {
        $mandante = $this->crearMandante();
        $proyectoA = $this->crearProyecto('servicio', $mandante);
        $proyectoB = $this->crearProyecto('servicio', $mandante);

        DB::table('estados_tecnicos')->insert([
            'proyecto_id' => $proyectoA->id,
            'codigo' => 'COMUN', 'nombre' => 'A', 'orden' => 100, 'activo' => true,
            'creada_en' => Carbon::now(), 'actualizada_en' => Carbon::now(),
        ]);

        $modeloB = ProyectoModel::query()->findOrFail($proyectoB->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(CatalogoEstadosTecnicos::class, ['proyecto' => $modeloB])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'COMUN')
            ->set('form.nombre', 'B')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(2, DB::table('estados_tecnicos')->where('codigo', 'COMUN')->count());
    }

    public function test_bloquea_eliminacion_si_hay_dependencias(): void
    {
        $proyecto = $this->crearProyectoServicio();
        $estadoId = (int) DB::table('estados_tecnicos')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'EN_USO', 'nombre' => 'En uso', 'orden' => 100, 'activo' => true,
            'creada_en' => Carbon::now(), 'actualizada_en' => Carbon::now(),
        ]);

        $this->insertarCasoServicio($proyecto, null, $estadoId);

        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(CatalogoEstadosTecnicos::class, ['proyecto' => $modelo])
            ->call('eliminar', $estadoId);

        $this->assertDatabaseHas('estados_tecnicos', ['id' => $estadoId]);
    }

    public function test_emite_evento_paso_completado_al_crear_primero(): void
    {
        $proyecto = $this->crearProyectoServicio();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(CatalogoEstadosTecnicos::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'PRIMERO')
            ->set('form.nombre', 'Primer estado')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('configuracion-paso-completado');
    }
}
