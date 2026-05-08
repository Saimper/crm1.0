<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy\ConfiguradorPasos;

use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\PasoResultados;
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
 * Notas de adaptación al schema real:
 *  - `resultados.tipo_gestion_id` NO existe en schema (CLAUDE.md §7.2 — acoplamiento
 *    operacional sin FK física). Los tests "crea_resultado_con_tipo_gestion_valido"
 *    y "rechaza_resultado_si_no_hay_tipos_de_gestion" del prompt se adaptan: el
 *    primero queda como "crea_resultado_con_codigo_unico_por_proyecto" (no hay
 *    relación de gestión); el segundo se omite porque no hay constraint que validar.
 *  - Tabla `subresultados` NO existe (auditoría P0 riesgo #1). Los tests
 *    "crea_subresultado_bajo_resultado" y "codigo_subresultado_unico_por_resultado_no_global"
 *    se omiten por ausencia de tabla.
 */
final class PasoResultadosTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_crea_resultado_con_codigo_unico_por_proyecto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        Livewire::test(PasoResultados::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'CONTACTO_EFECTIVO')
            ->set('form.nombre', 'Contacto efectivo')
            ->set('form.es_contacto_efectivo', true)
            ->set('form.requiere_compromiso', true)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('resultados', [
            'proyecto_id' => $proyecto->id,
            'codigo' => 'CONTACTO_EFECTIVO',
            'nombre' => 'Contacto efectivo',
            'es_contacto_efectivo' => true,
            'requiere_compromiso' => true,
            'requiere_causa' => false,
            'activo' => true,
        ]);
    }

    public function test_codigo_duplicado_en_otro_proyecto_permitido(): void
    {
        $mandante = $this->crearMandante();
        $proyectoA = $this->crearProyecto('cobranza', $mandante);
        $proyectoB = $this->crearProyecto('cx', $mandante);

        DB::table('resultados')->insert([
            'proyecto_id' => $proyectoA->id,
            'codigo' => 'COMUN',
            'nombre' => 'Resultado A',
            'orden' => 10,
            'activo' => true,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        $modeloB = ProyectoModel::query()->findOrFail($proyectoB->id);
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        Livewire::test(PasoResultados::class, ['proyecto' => $modeloB])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'COMUN')
            ->set('form.nombre', 'Resultado B')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('resultados', ['proyecto_id' => $proyectoA->id, 'codigo' => 'COMUN']);
        $this->assertDatabaseHas('resultados', ['proyecto_id' => $proyectoB->id, 'codigo' => 'COMUN']);
    }

    public function test_bloquea_eliminacion_resultado_con_gestiones(): void
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
            'codigo' => 'R_USADO',
            'nombre' => 'Resultado en uso',
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

        Livewire::test(PasoResultados::class, ['proyecto' => $modelo])
            ->call('eliminar', $resultadoId);

        $this->assertDatabaseHas('resultados', ['id' => $resultadoId]);
    }

    public function test_emite_evento_paso_completado_al_crear_primer_resultado(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        Livewire::test(PasoResultados::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.codigo', 'PRIMERO')
            ->set('form.nombre', 'Primer resultado')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('configuracion-paso-completado');
    }
}
