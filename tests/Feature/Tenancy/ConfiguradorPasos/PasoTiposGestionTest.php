<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy\ConfiguradorPasos;

use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\PasoTiposGestion;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Nota: el sub-test "bloquea_eliminacion_si_hay_resultados_dependientes" del prompt
 * NO aplica: la tabla `resultados` carece de columna `tipo_gestion_id` (acoplamiento
 * operacional sin FK física, CLAUDE.md §7.2 / auditoría P0). Se omite por ausencia
 * de columna; el chequeo equivalente vive solo a nivel de gestiones.tipo_gestion_id.
 */
final class PasoTiposGestionTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_crea_tipo_con_codigo_unico_por_proyecto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        Livewire::test(PasoTiposGestion::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'LLAMADA')
            ->set('form.nombre', 'Llamada')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tipos_gestion', [
            'proyecto_id' => $proyecto->id,
            'codigo' => 'LLAMADA',
            'nombre' => 'Llamada',
            'activo' => true,
        ]);
    }

    public function test_bloquea_eliminacion_si_hay_gestiones(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto, 'ABIERTO');
        $usuario = $this->crearGestor($proyecto);

        $tipoId = (int) DB::table('tipos_gestion')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'TG_USADO',
            'nombre' => 'Tipo en uso',
            'orden' => 10,
            'activo' => true,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
        $resultadoId = (int) DB::table('resultados')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'R_USADO',
            'nombre' => 'Resultado',
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
            'creada_en' => Carbon::now(),
        ]);

        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        Livewire::test(PasoTiposGestion::class, ['proyecto' => $modelo])
            ->call('eliminar', $tipoId);

        $this->assertDatabaseHas('tipos_gestion', ['id' => $tipoId]);
    }

    public function test_emite_evento_paso_completado_al_crear_primer_tipo(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        Livewire::test(PasoTiposGestion::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'EMAIL')
            ->set('form.nombre', 'Email')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('configuracion-paso-completado');
    }
}
