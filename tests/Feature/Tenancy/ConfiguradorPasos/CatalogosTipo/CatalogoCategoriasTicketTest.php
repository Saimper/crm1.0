<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy\ConfiguradorPasos\CatalogosTipo;

use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\CatalogosTipo\CatalogoCategoriasTicket;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\InsertaCti;
use Tests\TestCase;

final class CatalogoCategoriasTicketTest extends TestCase
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

        Livewire::test(CatalogoCategoriasTicket::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'SOPORTE')
            ->set('form.nombre', 'Soporte técnico')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categorias_ticket', [
            'proyecto_id' => $proyecto->id,
            'codigo' => 'SOPORTE',
        ]);
    }

    public function test_codigo_duplicado_en_otro_proyecto_permitido(): void
    {
        $mandante = $this->crearMandante();
        $proyectoA = $this->crearProyecto('cx', $mandante);
        $proyectoB = $this->crearProyecto('cx', $mandante);

        DB::table('categorias_ticket')->insert([
            'proyecto_id' => $proyectoA->id,
            'codigo' => 'COMUN',
            'nombre' => 'A',
            'orden' => 100,
            'activo' => true,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        $modeloB = ProyectoModel::query()->findOrFail($proyectoB->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(CatalogoCategoriasTicket::class, ['proyecto' => $modeloB])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'COMUN')
            ->set('form.nombre', 'B')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(2, DB::table('categorias_ticket')->where('codigo', 'COMUN')->count());
    }

    public function test_bloquea_eliminacion_si_hay_dependencias(): void
    {
        $proyecto = $this->crearProyectoCx();
        $categoriaId = (int) DB::table('categorias_ticket')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'EN_USO',
            'nombre' => 'En uso',
            'orden' => 100,
            'activo' => true,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        $this->insertarCasoTicketCx($proyecto, ['categoria_ticket_id' => $categoriaId]);

        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(CatalogoCategoriasTicket::class, ['proyecto' => $modelo])
            ->call('eliminar', $categoriaId);

        $this->assertDatabaseHas('categorias_ticket', ['id' => $categoriaId]);
    }

    public function test_emite_evento_paso_completado_al_crear_primero(): void
    {
        $proyecto = $this->crearProyectoCx();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(CatalogoCategoriasTicket::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'PRIMERA')
            ->set('form.nombre', 'Primera categoría')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('configuracion-paso-completado');
    }
}
