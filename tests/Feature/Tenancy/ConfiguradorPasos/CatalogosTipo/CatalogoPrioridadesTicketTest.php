<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy\ConfiguradorPasos\CatalogosTipo;

use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\CatalogosTipo\CatalogoPrioridadesTicket;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\InsertaCti;
use Tests\TestCase;

final class CatalogoPrioridadesTicketTest extends TestCase
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

        Livewire::test(CatalogoPrioridadesTicket::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'ALTA')
            ->set('form.nombre', 'Alta')
            ->set('form.peso', 200)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('prioridades_ticket', [
            'proyecto_id' => $proyecto->id,
            'codigo' => 'ALTA',
            'peso' => 200,
        ]);
    }

    public function test_codigo_duplicado_en_otro_proyecto_permitido(): void
    {
        $mandante = $this->crearMandante();
        $proyectoA = $this->crearProyecto('cx', $mandante);
        $proyectoB = $this->crearProyecto('cx', $mandante);

        DB::table('prioridades_ticket')->insert([
            'proyecto_id' => $proyectoA->id,
            'codigo' => 'COMUN', 'nombre' => 'A', 'peso' => 100, 'orden' => 100, 'activo' => true,
            'creada_en' => Carbon::now(), 'actualizada_en' => Carbon::now(),
        ]);

        $modeloB = ProyectoModel::query()->findOrFail($proyectoB->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(CatalogoPrioridadesTicket::class, ['proyecto' => $modeloB])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'COMUN')
            ->set('form.nombre', 'B')
            ->set('form.peso', 100)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(2, DB::table('prioridades_ticket')->where('codigo', 'COMUN')->count());
    }

    public function test_bloquea_eliminacion_si_hay_dependencias(): void
    {
        $proyecto = $this->crearProyectoCx();
        $prioridadId = (int) DB::table('prioridades_ticket')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'EN_USO', 'nombre' => 'En uso', 'peso' => 100, 'orden' => 100, 'activo' => true,
            'creada_en' => Carbon::now(), 'actualizada_en' => Carbon::now(),
        ]);

        $this->insertarCasoTicketCx($proyecto, ['prioridad_ticket_id' => $prioridadId]);

        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(CatalogoPrioridadesTicket::class, ['proyecto' => $modelo])
            ->call('eliminar', $prioridadId);

        $this->assertDatabaseHas('prioridades_ticket', ['id' => $prioridadId]);
    }

    public function test_emite_evento_paso_completado_al_crear_primero(): void
    {
        $proyecto = $this->crearProyectoCx();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(CatalogoPrioridadesTicket::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'PRIMERA')
            ->set('form.nombre', 'Primera prioridad')
            ->set('form.peso', 100)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('configuracion-paso-completado');
    }
}
