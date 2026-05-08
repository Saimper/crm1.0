<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy\ConfiguradorPasos\CatalogosTipo;

use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\CatalogosTipo\CatalogoNivelesSla;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\InsertaCti;
use Tests\TestCase;

final class CatalogoNivelesSlaTest extends TestCase
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

        Livewire::test(CatalogoNivelesSla::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'GOLD')
            ->set('form.nombre', 'Gold 4h')
            ->set('form.horas_resolucion', 4)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('niveles_sla', [
            'proyecto_id' => $proyecto->id,
            'codigo' => 'GOLD',
            'horas_resolucion' => 4,
        ]);
    }

    public function test_codigo_duplicado_en_otro_proyecto_permitido(): void
    {
        $mandante = $this->crearMandante();
        $proyectoA = $this->crearProyecto('cx', $mandante);
        $proyectoB = $this->crearProyecto('cx', $mandante);

        DB::table('niveles_sla')->insert([
            'proyecto_id' => $proyectoA->id,
            'codigo' => 'COMUN', 'nombre' => 'A', 'horas_resolucion' => 24, 'orden' => 100, 'activo' => true,
            'creada_en' => Carbon::now(), 'actualizada_en' => Carbon::now(),
        ]);

        $modeloB = ProyectoModel::query()->findOrFail($proyectoB->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(CatalogoNivelesSla::class, ['proyecto' => $modeloB])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'COMUN')
            ->set('form.nombre', 'B')
            ->set('form.horas_resolucion', 24)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(2, DB::table('niveles_sla')->where('codigo', 'COMUN')->count());
    }

    public function test_bloquea_eliminacion_si_hay_dependencias(): void
    {
        $proyecto = $this->crearProyectoCx();
        $nivelId = (int) DB::table('niveles_sla')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'EN_USO', 'nombre' => 'En uso', 'horas_resolucion' => 24, 'orden' => 100, 'activo' => true,
            'creada_en' => Carbon::now(), 'actualizada_en' => Carbon::now(),
        ]);

        $this->insertarCasoTicketCx($proyecto, ['nivel_sla_id' => $nivelId]);

        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(CatalogoNivelesSla::class, ['proyecto' => $modelo])
            ->call('eliminar', $nivelId);

        $this->assertDatabaseHas('niveles_sla', ['id' => $nivelId]);
    }

    public function test_emite_evento_paso_completado_al_crear_primero(): void
    {
        $proyecto = $this->crearProyectoCx();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(CatalogoNivelesSla::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'PRIMERO')
            ->set('form.nombre', 'Primer nivel')
            ->set('form.horas_resolucion', 24)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('configuracion-paso-completado');
    }
}
