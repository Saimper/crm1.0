<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy\ConfiguradorPasos\CatalogosTipo;

use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\CatalogosTipo\CatalogoNivelesEscalamiento;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\InsertaCti;
use Tests\TestCase;

final class CatalogoNivelesEscalamientoTest extends TestCase
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
        $proyecto = $this->crearProyectoCx();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(CatalogoNivelesEscalamiento::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'NIVEL_1')
            ->set('form.nombre', 'Primer nivel')
            ->set('form.nivel', 1)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('niveles_escalamiento', [
            'proyecto_id' => $proyecto->id,
            'codigo' => 'NIVEL_1',
            'nivel' => 1,
        ]);
    }

    public function test_codigo_duplicado_en_otro_proyecto_permitido(): void
    {
        $mandante = $this->crearMandante();
        $proyectoA = $this->crearProyecto('cx', $mandante);
        $proyectoB = $this->crearProyecto('cx', $mandante);

        DB::table('niveles_escalamiento')->insert([
            'proyecto_id' => $proyectoA->id,
            'codigo' => 'COMUN', 'nombre' => 'A', 'nivel' => 1, 'orden' => 100, 'activo' => true,
            'creada_en' => Carbon::now(), 'actualizada_en' => Carbon::now(),
        ]);

        $modeloB = ProyectoModel::query()->findOrFail($proyectoB->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(CatalogoNivelesEscalamiento::class, ['proyecto' => $modeloB])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'COMUN')
            ->set('form.nombre', 'B')
            ->set('form.nivel', 1)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(2, DB::table('niveles_escalamiento')->where('codigo', 'COMUN')->count());
    }

    public function test_bloquea_eliminacion_si_hay_dependencias(): void
    {
        $proyecto = $this->crearProyectoCx();
        $nivelId = (int) DB::table('niveles_escalamiento')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'EN_USO', 'nombre' => 'En uso', 'nivel' => 5, 'orden' => 100, 'activo' => true,
            'creada_en' => Carbon::now(), 'actualizada_en' => Carbon::now(),
        ]);

        $this->insertarCasoTicketCx($proyecto, ['nivel_escalamiento_id' => $nivelId]);

        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(CatalogoNivelesEscalamiento::class, ['proyecto' => $modelo])
            ->call('eliminar', $nivelId);

        $this->assertDatabaseHas('niveles_escalamiento', ['id' => $nivelId]);
    }

    public function test_emite_evento_paso_completado_al_crear_primero(): void
    {
        $proyecto = $this->crearProyectoCx();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(CatalogoNivelesEscalamiento::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'PRIMERO')
            ->set('form.nombre', 'Primer nivel')
            ->set('form.nivel', 1)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('configuracion-paso-completado');
    }
}
