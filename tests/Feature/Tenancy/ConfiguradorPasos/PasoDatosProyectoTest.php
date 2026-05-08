<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy\ConfiguradorPasos;

use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\PasoDatosProyecto;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class PasoDatosProyectoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_global_puede_editar_nombre_y_codigo(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        Livewire::test(PasoDatosProyecto::class, ['proyecto' => $modelo])
            ->set('nombre', 'Proyecto editado F36')
            ->set('codigo', 'PROYECTO_EDITADO_F36')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('proyectos', [
            'id' => $proyecto->id,
            'nombre' => 'Proyecto editado F36',
            'codigo' => 'PROYECTO_EDITADO_F36',
        ]);
    }

    public function test_codigo_duplicado_dentro_del_mandante_falla(): void
    {
        $mandante = $this->crearMandante();
        $existente = $this->crearProyecto('cobranza', $mandante, 'YA_EXISTE');
        $editado = $this->crearProyecto('cx', $mandante, 'OTRO_CODIGO');
        $modelo = ProyectoModel::query()->findOrFail($editado->id);

        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        Livewire::test(PasoDatosProyecto::class, ['proyecto' => $modelo])
            ->set('codigo', 'YA_EXISTE')
            ->call('guardar')
            ->assertHasErrors(['codigo']);

        // Confirmar que el código no se cambió.
        $this->assertSame(
            'OTRO_CODIGO',
            (string) DB::table('proyectos')->where('id', $editado->id)->value('codigo'),
        );

        // Mantengo el otro proyecto intacto.
        $this->assertSame(
            'YA_EXISTE',
            (string) DB::table('proyectos')->where('id', $existente->id)->value('codigo'),
        );
    }

    public function test_no_permite_cambiar_tipo_operacion(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        Livewire::test(PasoDatosProyecto::class, ['proyecto' => $modelo])
            ->set('tipoOperacion', 'venta')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(
            'cobranza',
            (string) DB::table('proyectos')->where('id', $proyecto->id)->value('tipo_operacion'),
        );
    }

    public function test_emite_evento_paso_completado_al_guardar(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        Livewire::test(PasoDatosProyecto::class, ['proyecto' => $modelo])
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('configuracion-paso-completado');
    }
}
