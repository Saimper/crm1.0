<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy\ConfiguradorPasos\CatalogosTipo;

use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\CatalogosTipo\CatalogoTiposPago;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\InsertaCti;
use Tests\TestCase;

final class CatalogoTiposPagoTest extends TestCase
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

        Livewire::test(CatalogoTiposPago::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'EFECTIVO')
            ->set('form.nombre', 'Efectivo')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tipos_pago', [
            'proyecto_id' => $proyecto->id,
            'codigo' => 'EFECTIVO',
        ]);
    }

    public function test_codigo_duplicado_en_otro_proyecto_permitido(): void
    {
        $mandante = $this->crearMandante();
        $proyectoA = $this->crearProyecto('cobranza', $mandante);
        $proyectoB = $this->crearProyecto('cobranza', $mandante);

        DB::table('tipos_pago')->insert([
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

        Livewire::test(CatalogoTiposPago::class, ['proyecto' => $modeloB])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'COMUN')
            ->set('form.nombre', 'B')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(2, DB::table('tipos_pago')->where('codigo', 'COMUN')->count());
    }

    public function test_bloquea_eliminacion_si_hay_dependencias(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $tipoId = (int) DB::table('tipos_pago')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'EN_USO',
            'nombre' => 'En uso',
            'orden' => 100,
            'activo' => true,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        $this->insertarCompromisoPromesaPagoConTipoPago($proyecto, $tipoId);

        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(CatalogoTiposPago::class, ['proyecto' => $modelo])
            ->call('eliminar', $tipoId);

        $this->assertDatabaseHas('tipos_pago', ['id' => $tipoId]);
    }

    public function test_emite_evento_paso_completado_al_crear_primero(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(CatalogoTiposPago::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'PRIMERO')
            ->set('form.nombre', 'Primer tipo')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('configuracion-paso-completado');
    }
}
