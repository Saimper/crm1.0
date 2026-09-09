<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Gestiones;

use App\Modules\Casos\Infrastructure\Http\Livewire\NuevaGestion;
use App\Modules\Gestiones\Domain\Exceptions\ResultadoNoAdmitidoPorTipo;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\PasoMotivosNoContacto;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\PasoResultados;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Qué resultados admite cada tipo de gestión.
 *
 * El selector de resultado mostraba los nueve del proyecto sin importar el tipo,
 * porque no había ninguna relación entre las dos tablas: «Promesa de pago
 * fraccionado» aparecía como resultado posible de un «No contactado».
 *
 * La regla de arranque es EN ABIERTO y POR TIPO: un tipo sin ninguna combinación
 * declarada admite todos los resultados. Con la regla al revés, los proyectos
 * que hoy no tienen esto configurado —que son todos— se quedarían sin poder
 * registrar una gestión el día del despliegue.
 */
final class CascadaTipoResultadoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_sin_tipo_elegido_no_hay_resultados_que_ofrecer(): void
    {
        [$proyecto, $casoId, $personaId] = $this->escenario();
        $gestor = $this->crearGestor($proyecto);
        $this->activarProyecto($proyecto);

        $resultados = Livewire::actingAs($gestor)
            ->test(NuevaGestion::class, ['casoId' => $casoId, 'personaId' => $personaId, 'tipoCaso' => 'cobranza'])
            ->viewData('resultados');

        $this->assertCount(0, $resultados);
    }

    public function test_un_tipo_sin_combinaciones_admite_todos_los_resultados(): void
    {
        [$proyecto, $casoId, $personaId, $tipoA, , $r1, $r2] = $this->escenario();
        $gestor = $this->crearGestor($proyecto);
        $this->activarProyecto($proyecto);

        $ids = collect(Livewire::actingAs($gestor)
            ->test(NuevaGestion::class, ['casoId' => $casoId, 'personaId' => $personaId, 'tipoCaso' => 'cobranza'])
            ->set('tipoGestionId', $tipoA)
            ->viewData('resultados'))->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$r1, $r2], $ids);
    }

    public function test_con_combinaciones_declaradas_solo_salen_las_suyas(): void
    {
        [$proyecto, $casoId, $personaId, $tipoA, , $r1, $r2] = $this->escenario();
        $this->declarar($proyecto, $tipoA, $r1);
        $gestor = $this->crearGestor($proyecto);
        $this->activarProyecto($proyecto);

        $ids = collect(Livewire::actingAs($gestor)
            ->test(NuevaGestion::class, ['casoId' => $casoId, 'personaId' => $personaId, 'tipoCaso' => 'cobranza'])
            ->set('tipoGestionId', $tipoA)
            ->viewData('resultados'))->pluck('id')->all();

        $this->assertSame([$r1], $ids);
        $this->assertNotContains($r2, $ids);
    }

    /** Cambiar de tipo no puede dejar puesto un resultado que ya no vale. */
    public function test_cambiar_de_tipo_limpia_el_resultado_elegido(): void
    {
        [$proyecto, $casoId, $personaId, $tipoA, $tipoB, $r1] = $this->escenario();
        $gestor = $this->crearGestor($proyecto);
        $this->activarProyecto($proyecto);

        Livewire::actingAs($gestor)
            ->test(NuevaGestion::class, ['casoId' => $casoId, 'personaId' => $personaId, 'tipoCaso' => 'cobranza'])
            ->set('tipoGestionId', $tipoA)
            ->set('resultadoId', $r1)
            ->set('tipoGestionId', $tipoB)
            ->assertSet('resultadoId', null);
    }

    /**
     * El `resultadoId` viaja en una propiedad pública de Livewire: la lista
     * filtrada del `<select>` no es una defensa (§11, segunda capa).
     */
    public function test_el_dominio_rechaza_un_par_que_el_selector_no_ofrecia(): void
    {
        [$proyecto, $casoId, $personaId, $tipoA, , $r1, $r2] = $this->escenario();
        $this->declarar($proyecto, $tipoA, $r1);
        $gestor = $this->crearGestor($proyecto);
        $this->activarProyecto($proyecto);

        Livewire::actingAs($gestor)
            ->test(NuevaGestion::class, ['casoId' => $casoId, 'personaId' => $personaId, 'tipoCaso' => 'cobranza'])
            ->set('canalId', (int) DB::table('canales')->value('id'))
            ->set('tipoGestionId', $tipoA)
            ->set('resultadoId', $r2)
            ->call('guardar')
            ->assertHasErrors('general');

        $this->assertDatabaseCount('gestiones', 0);
    }

    public function test_la_excepcion_de_dominio_dice_el_par_que_falla(): void
    {
        $e = ResultadoNoAdmitidoPorTipo::para(7, 9);

        $this->assertStringContainsString('9', $e->getMessage());
        $this->assertStringContainsString('7', $e->getMessage());
    }

    /** Multi-tenancy (§12): la matriz de un proyecto no se toca desde otro. */
    public function test_no_se_puede_declarar_un_par_de_otro_proyecto(): void
    {
        [$proyectoA, , , $tipoA] = $this->escenario();
        [$proyectoB, , , , , $rB] = $this->escenario();
        $admin = $this->crearAdminGlobal();

        Livewire::actingAs($admin)
            ->test(PasoResultados::class, ['proyecto' => ProyectoModel::find($proyectoA->id)])
            ->call('alternarCombinacion', $tipoA, $rB)
            ->assertForbidden();

        $this->assertDatabaseCount('resultado_tipo_gestion', 0);
    }

    public function test_la_matriz_marca_y_desmarca(): void
    {
        [$proyecto, , , $tipoA, , $r1] = $this->escenario();
        $admin = $this->crearAdminGlobal();

        $componente = Livewire::actingAs($admin)
            ->test(PasoResultados::class, ['proyecto' => ProyectoModel::find($proyecto->id)]);

        $componente->call('alternarCombinacion', $tipoA, $r1);
        $this->assertDatabaseHas('resultado_tipo_gestion', [
            'proyecto_id' => $proyecto->id, 'tipo_gestion_id' => $tipoA, 'resultado_id' => $r1,
        ]);

        $componente->call('alternarCombinacion', $tipoA, $r1);
        $this->assertDatabaseMissing('resultado_tipo_gestion', [
            'proyecto_id' => $proyecto->id, 'tipo_gestion_id' => $tipoA, 'resultado_id' => $r1,
        ]);
    }

    /**
     * `causas_gestion` no tenía pantalla en ninguna parte. Cuatro de los nueve
     * resultados del proyecto de cobranza exigen causa y la tabla estaba vacía:
     * esas cuatro gestiones eran imposibles de guardar.
     */
    public function test_ahora_se_pueden_crear_causas_de_gestion(): void
    {
        [$proyecto] = $this->escenario();
        $admin = $this->crearAdminGlobal();

        Livewire::actingAs($admin)
            ->test(PasoMotivosNoContacto::class, ['proyecto' => ProyectoModel::find($proyecto->id)])
            ->set('causaNueva', 'Cliente fallecido')
            ->call('crearCausa')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('causas_gestion', [
            'proyecto_id' => $proyecto->id,
            'codigo' => 'CLIENTE_FALLECIDO',
            'nombre' => 'Cliente fallecido',
        ]);
    }

    public function test_una_causa_usada_en_una_gestion_no_se_borra(): void
    {
        [$proyecto, $casoId, $personaId, $tipoA, , $r1] = $this->escenario();

        $causaId = (int) DB::table('causas_gestion')->insertGetId([
            'proyecto_id' => $proyecto->id, 'codigo' => 'X', 'nombre' => 'X',
            'activo' => true, 'orden' => 0, 'creada_en' => Carbon::now(), 'actualizada_en' => Carbon::now(),
        ]);

        DB::table('gestiones')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id, 'caso_id' => $casoId, 'persona_id' => $personaId,
            'canal_id' => (int) DB::table('canales')->value('id'),
            'tipo_gestion_id' => $tipoA, 'resultado_id' => $r1, 'causa_id' => $causaId,
            'usuario_id' => $this->crearGestor($proyecto)->id,
            'creada_en' => Carbon::now(), 'actualizada_en' => Carbon::now(),
        ]);

        $admin = $this->crearAdminGlobal();
        Livewire::actingAs($admin)
            ->test(PasoMotivosNoContacto::class, ['proyecto' => ProyectoModel::find($proyecto->id)])
            ->call('eliminarCausa', $causaId);

        $this->assertDatabaseHas('causas_gestion', ['id' => $causaId]);
    }

    private function declarar(stdClass $proyecto, int $tipoId, int $resultadoId): void
    {
        DB::table('resultado_tipo_gestion')->insert([
            'proyecto_id' => $proyecto->id,
            'tipo_gestion_id' => $tipoId,
            'resultado_id' => $resultadoId,
            'orden' => 0,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
    }

    /** @return array{0: stdClass, 1: int, 2: int, 3: int, 4: int, 5: int, 6: int} */
    private function escenario(): array
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto, 'ABIERTO');
        $persona = $this->crearPersonaEn($proyecto);

        $casoId = (int) DB::table('casos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id, 'cartera_id' => $cartera->id, 'persona_id' => $persona->id,
            'tipo_caso' => 'cobranza', 'estado_caso_id' => $estado->id,
            'fecha_ingreso' => Carbon::today(),
            'creada_en' => Carbon::now(), 'actualizada_en' => Carbon::now(),
        ]);

        $tipoA = $this->catalogo($proyecto, 'tipos_gestion', 'CONTACTADO', 10);
        $tipoB = $this->catalogo($proyecto, 'tipos_gestion', 'NO_CONTACTADO', 20);
        $r1 = $this->resultado($proyecto, 'CONTACTO_TITULAR', 10);
        $r2 = $this->resultado($proyecto, 'BUZON', 20);

        return [$proyecto, $casoId, (int) $persona->id, $tipoA, $tipoB, $r1, $r2];
    }

    private function catalogo(stdClass $proyecto, string $tabla, string $codigo, int $orden): int
    {
        return (int) DB::table($tabla)->insertGetId([
            'proyecto_id' => $proyecto->id, 'codigo' => $codigo, 'nombre' => $codigo,
            'activo' => true, 'orden' => $orden,
            'creada_en' => Carbon::now(), 'actualizada_en' => Carbon::now(),
        ]);
    }

    private function resultado(stdClass $proyecto, string $codigo, int $orden): int
    {
        return (int) DB::table('resultados')->insertGetId([
            'proyecto_id' => $proyecto->id, 'codigo' => $codigo, 'nombre' => $codigo,
            'activo' => true, 'orden' => $orden,
            'es_contacto_efectivo' => false, 'requiere_compromiso' => false, 'requiere_causa' => false,
            'creada_en' => Carbon::now(), 'actualizada_en' => Carbon::now(),
        ]);
    }
}
