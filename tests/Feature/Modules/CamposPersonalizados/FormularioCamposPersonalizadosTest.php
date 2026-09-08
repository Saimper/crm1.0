<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\CamposPersonalizados;

use App\Models\User;
use App\Modules\CamposPersonalizados\Infrastructure\Http\Livewire\FormularioCamposPersonalizados;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Quién puede escribir valores de campos personalizados desde el formulario.
 *
 * `campos.editar` es el permiso de VALORES (no de definiciones, §7): lo tienen
 * ADMIN_GLOBAL, SUPERVISOR y GESTOR; el AUDITOR y quien no tenga rol en el
 * proyecto entran bloqueados y `guardar()` aborta con 403 aunque manipulen el
 * payload — la defensa está en el componente, no en el estado del front.
 */
final class FormularioCamposPersonalizadosTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_global_puede_guardar_valor(): void
    {
        ['casoId' => $casoId, 'proyecto' => $proyecto, 'cartera' => $cartera] = $this->crearContexto();
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(FormularioCamposPersonalizados::class, [
            'proyectoId' => (int) $proyecto->id,
            'ambito' => 'caso',
            'ambitoId' => (int) $cartera->id,
            'entidadId' => $casoId,
        ])
            ->set('valores.operador_externo', 'Agente Admin')
            ->call('guardar')
            ->assertHasNoErrors();

        $campoId = (int) DB::table('campos_personalizados')
            ->where('proyecto_id', $proyecto->id)
            ->where('codigo', 'operador_externo')
            ->value('id');

        $this->assertDatabaseHas('valores_campo_personalizado', [
            'campo_personalizado_id' => $campoId,
            'entidad_id' => $casoId,
            'valor_texto_corto' => 'Agente Admin',
        ]);
    }

    public function test_supervisor_con_permiso_puede_guardar_valor(): void
    {
        ['casoId' => $casoId, 'proyecto' => $proyecto, 'cartera' => $cartera] = $this->crearContexto();
        $this->actingAs($this->crearSupervisor($proyecto));

        Livewire::test(FormularioCamposPersonalizados::class, [
            'proyectoId' => (int) $proyecto->id,
            'ambito' => 'caso',
            'ambitoId' => (int) $cartera->id,
            'entidadId' => $casoId,
        ])
            ->set('valores.operador_externo', 'Supervisor editó')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('valores_campo_personalizado', [
            'entidad_id' => $casoId,
            'valor_texto_corto' => 'Supervisor editó',
        ]);
    }

    public function test_gestor_con_permiso_puede_guardar_valor(): void
    {
        ['casoId' => $casoId, 'proyecto' => $proyecto, 'cartera' => $cartera] = $this->crearContexto();
        $this->actingAs($this->crearGestor($proyecto));

        Livewire::test(FormularioCamposPersonalizados::class, [
            'proyectoId' => (int) $proyecto->id,
            'ambito' => 'caso',
            'ambitoId' => (int) $cartera->id,
            'entidadId' => $casoId,
        ])
            ->set('valores.operador_externo', 'Gestor editó')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('valores_campo_personalizado', [
            'entidad_id' => $casoId,
            'valor_texto_corto' => 'Gestor editó',
        ]);
    }

    public function test_auditor_sin_permiso_campos_editar_se_monta_bloqueado_y_guardar_aborta(): void
    {
        ['casoId' => $casoId, 'proyecto' => $proyecto, 'cartera' => $cartera] = $this->crearContexto();
        $this->actingAs($this->crearAuditor($proyecto));

        Livewire::test(FormularioCamposPersonalizados::class, [
            'proyectoId' => (int) $proyecto->id,
            'ambito' => 'caso',
            'ambitoId' => (int) $cartera->id,
            'entidadId' => $casoId,
        ])
            ->assertSet('bloqueado', true)
            ->set('valores.operador_externo', 'Intento auditor')
            ->call('guardar')
            ->assertStatus(403);

        $this->assertSame(0, DB::table('valores_campo_personalizado')->count());
    }

    public function test_usuario_sin_rol_en_proyecto_bloqueado_y_aborta(): void
    {
        ['casoId' => $casoId, 'proyecto' => $proyecto, 'cartera' => $cartera] = $this->crearContexto();
        $this->actingAs(User::factory()->create());

        Livewire::test(FormularioCamposPersonalizados::class, [
            'proyectoId' => (int) $proyecto->id,
            'ambito' => 'caso',
            'ambitoId' => (int) $cartera->id,
            'entidadId' => $casoId,
        ])
            ->assertSet('bloqueado', true)
            ->call('guardar')
            ->assertStatus(403);

        $this->assertSame(0, DB::table('valores_campo_personalizado')->count());
    }

    /**
     * Proyecto de cobranza con una cartera, un caso suyo y un campo
     * personalizado de ámbito caso — que es lo que el formulario pinta.
     *
     * @return array{casoId: int, proyecto: stdClass, cartera: stdClass}
     */
    private function crearContexto(): array
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto, 'CONSUMO');
        $this->activarProyecto($proyecto);

        $casoId = $this->crearCasoEn($proyecto, [
            'cartera' => $cartera,
            'persona' => $this->crearPersonaEn($proyecto),
            'estado' => $this->crearEstadoCasoEn($proyecto, 'ABIERTO'),
            'fecha_ingreso' => '2026-04-17',
        ]);

        DB::table('campos_personalizados')->insert([
            'proyecto_id' => $proyecto->id,
            'ambito' => 'caso',
            'ambito_id' => $cartera->id,
            'grupo_campo_id' => null,
            'tipo' => 'texto_corto',
            'codigo' => 'operador_externo',
            'etiqueta' => 'Operador externo',
            'obligatorio' => false,
            'activo' => true,
            'visible_en_gestion' => true,
            'orden' => 10,
            'reglas' => json_encode([]),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        return ['casoId' => $casoId, 'proyecto' => $proyecto, 'cartera' => $cartera];
    }
}
