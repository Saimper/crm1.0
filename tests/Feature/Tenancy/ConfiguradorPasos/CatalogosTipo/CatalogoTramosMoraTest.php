<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy\ConfiguradorPasos\CatalogosTipo;

use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\CatalogosTipo\CatalogoTramosMora;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\InsertaCti;
use Tests\TestCase;

final class CatalogoTramosMoraTest extends TestCase
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
        $proyecto = $this->crearProyectoCobranza();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(CatalogoTramosMora::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'MORA_30_60')
            ->set('form.nombre', '30-60 días')
            ->set('form.dias_desde', 30)
            ->set('form.dias_hasta', 60)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tramos_mora', [
            'proyecto_id' => $proyecto->id,
            'codigo' => 'MORA_30_60',
            'dias_desde' => 30,
            'dias_hasta' => 60,
        ]);
    }

    public function test_codigo_duplicado_en_otro_proyecto_permitido(): void
    {
        $mandante = $this->crearMandante();
        $proyectoA = $this->crearProyecto('cobranza', $mandante);
        $proyectoB = $this->crearProyecto('cobranza', $mandante);

        DB::table('tramos_mora')->insert([
            'proyecto_id' => $proyectoA->id,
            'codigo' => 'COMUN',
            'nombre' => 'A',
            'dias_desde' => 0,
            'orden' => 100,
            'activo' => true,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        $modeloB = ProyectoModel::query()->findOrFail($proyectoB->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(CatalogoTramosMora::class, ['proyecto' => $modeloB])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'COMUN')
            ->set('form.nombre', 'B')
            ->set('form.dias_desde', 0)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(2, DB::table('tramos_mora')->where('codigo', 'COMUN')->count());
    }

    public function test_bloquea_eliminacion_si_hay_dependencias(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $tramoId = (int) DB::table('tramos_mora')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'EN_USO',
            'nombre' => 'En uso',
            'dias_desde' => 0,
            'orden' => 100,
            'activo' => true,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        $this->insertarCasoCobranzaConTramoMora($proyecto, $tramoId);

        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(CatalogoTramosMora::class, ['proyecto' => $modelo])
            ->call('eliminar', $tramoId);

        $this->assertDatabaseHas('tramos_mora', ['id' => $tramoId]);
    }

    public function test_emite_evento_paso_completado_al_crear_primero(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(CatalogoTramosMora::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'PRIMERO')
            ->set('form.nombre', 'Primer tramo')
            ->set('form.dias_desde', 0)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('configuracion-paso-completado');
    }
}
