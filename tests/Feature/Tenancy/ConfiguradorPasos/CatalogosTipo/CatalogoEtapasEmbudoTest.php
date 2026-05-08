<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy\ConfiguradorPasos\CatalogosTipo;

use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\CatalogosTipo\CatalogoEtapasEmbudo;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\InsertaCti;
use Tests\TestCase;

final class CatalogoEtapasEmbudoTest extends TestCase
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
        $proyecto = $this->crearProyectoVenta();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(CatalogoEtapasEmbudo::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'PROSPECTO')
            ->set('form.nombre', 'Prospecto')
            ->set('form.nivel', 1)
            ->set('form.probabilidad_cierre', 10)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('etapas_embudo', [
            'proyecto_id' => $proyecto->id,
            'codigo' => 'PROSPECTO',
            'nivel' => 1,
        ]);
    }

    public function test_codigo_duplicado_en_otro_proyecto_permitido(): void
    {
        $mandante = $this->crearMandante();
        $proyectoA = $this->crearProyecto('venta', $mandante);
        $proyectoB = $this->crearProyecto('venta', $mandante);

        DB::table('etapas_embudo')->insert([
            'proyecto_id' => $proyectoA->id,
            'codigo' => 'COMUN', 'nombre' => 'A', 'nivel' => 1, 'probabilidad_cierre' => 10,
            'orden' => 100, 'activo' => true,
            'creada_en' => Carbon::now(), 'actualizada_en' => Carbon::now(),
        ]);

        $modeloB = ProyectoModel::query()->findOrFail($proyectoB->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(CatalogoEtapasEmbudo::class, ['proyecto' => $modeloB])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'COMUN')
            ->set('form.nombre', 'B')
            ->set('form.nivel', 1)
            ->set('form.probabilidad_cierre', 10)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(2, DB::table('etapas_embudo')->where('codigo', 'COMUN')->count());
    }

    public function test_bloquea_eliminacion_si_hay_dependencias(): void
    {
        $proyecto = $this->crearProyectoVenta();
        $etapaId = (int) DB::table('etapas_embudo')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'EN_USO', 'nombre' => 'En uso', 'nivel' => 1, 'probabilidad_cierre' => 10,
            'orden' => 100, 'activo' => true,
            'creada_en' => Carbon::now(), 'actualizada_en' => Carbon::now(),
        ]);

        $this->insertarCasoLeadVenta($proyecto, null, $etapaId);

        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(CatalogoEtapasEmbudo::class, ['proyecto' => $modelo])
            ->call('eliminar', $etapaId);

        $this->assertDatabaseHas('etapas_embudo', ['id' => $etapaId]);
    }

    public function test_emite_evento_paso_completado_al_crear_primero(): void
    {
        $proyecto = $this->crearProyectoVenta();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(CatalogoEtapasEmbudo::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'PRIMERA')
            ->set('form.nombre', 'Primera etapa')
            ->set('form.nivel', 1)
            ->set('form.probabilidad_cierre', 5)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('configuracion-paso-completado');
    }
}
