<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy\ConfiguradorPasos;

use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\PasoMotivosNoContacto;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class PasoMotivosNoContactoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_crea_motivo_con_codigo_unico_por_proyecto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        Livewire::test(PasoMotivosNoContacto::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'BUZON_VOZ')
            ->set('form.nombre', 'Buzón de voz')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('motivos_no_contacto', [
            'proyecto_id' => $proyecto->id,
            'codigo' => 'BUZON_VOZ',
            'nombre' => 'Buzón de voz',
            'activo' => true,
        ]);
    }

    public function test_bloquea_eliminacion_si_hay_gestiones_con_ese_motivo(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto, 'ABIERTO');
        $usuario = $this->crearGestor($proyecto);

        $tipoId = (int) DB::table('tipos_gestion')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'TG',
            'nombre' => 'Tipo',
            'orden' => 10,
            'activo' => true,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
        $resultadoId = (int) DB::table('resultados')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'R',
            'nombre' => 'Resultado',
            'orden' => 10,
            'activo' => true,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
        $motivoId = (int) DB::table('motivos_no_contacto')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'MNC_EN_USO',
            'nombre' => 'Motivo en uso',
            'orden' => 10,
            'activo' => true,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
        $canalId = (int) DB::table('canales')->value('id');

        $casoId = (int) DB::table('casos')->insertGetId([
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

        DB::table('gestiones')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'caso_id' => $casoId,
            'persona_id' => $persona->id,
            'usuario_id' => $usuario->id,
            'canal_id' => $canalId,
            'tipo_gestion_id' => $tipoId,
            'resultado_id' => $resultadoId,
            'motivo_no_contacto_id' => $motivoId,
            'creada_en' => Carbon::now(),
        ]);

        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        Livewire::test(PasoMotivosNoContacto::class, ['proyecto' => $modelo])
            ->call('eliminar', $motivoId);

        $this->assertDatabaseHas('motivos_no_contacto', ['id' => $motivoId]);
    }

    public function test_emite_evento_paso_completado_al_crear_primer_motivo(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        Livewire::test(PasoMotivosNoContacto::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'PRIMERO')
            ->set('form.nombre', 'Primer motivo')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('configuracion-paso-completado');
    }
}
