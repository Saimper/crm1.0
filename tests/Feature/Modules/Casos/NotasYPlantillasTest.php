<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Casos;

use App\Modules\Casos\Infrastructure\Http\Livewire\NuevaGestion;
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
 * El campo de notas y sus frases hechas.
 *
 * Las «chips de plantillas» no son un detalle de UI: son un catálogo. Cada
 * mandante habla distinto y en cobranza no se escribe lo mismo que en soporte,
 * así que van por proyecto, y pueden colgar de un resultado —que es cuando de
 * verdad ahorran escribir—.
 *
 * Lo que NO hacen: no ejecutan nada, no rellenan otros campos y no condicionan
 * el formulario. Pegan texto en el textarea y el gestor lo edita.
 */
final class NotasYPlantillasTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_las_plantillas_generales_salen_siempre(): void
    {
        [$proyecto, $casoId, $personaId] = $this->escenario();
        $this->plantilla($proyecto, 'Buzón', 'Se dejó mensaje de voz.', null);
        $gestor = $this->crearGestor($proyecto);
        $this->activarProyecto($proyecto);

        $etiquetas = collect(Livewire::actingAs($gestor)
            ->test(NuevaGestion::class, ['casoId' => $casoId, 'personaId' => $personaId, 'tipoCaso' => 'cobranza'])
            ->viewData('plantillasNota'))->pluck('etiqueta')->all();

        $this->assertContains('Buzón', $etiquetas);
    }

    public function test_las_plantillas_de_un_resultado_solo_salen_con_ese_resultado(): void
    {
        [$proyecto, $casoId, $personaId, $tipoId, $resultadoId] = $this->escenario();
        $this->plantilla($proyecto, 'Pide llamada', 'El titular pide que se le llame después de las 6.', $resultadoId);
        $gestor = $this->crearGestor($proyecto);
        $this->activarProyecto($proyecto);

        $componente = Livewire::actingAs($gestor)
            ->test(NuevaGestion::class, ['casoId' => $casoId, 'personaId' => $personaId, 'tipoCaso' => 'cobranza']);

        $this->assertCount(0, $componente->viewData('plantillasNota'), 'sin resultado elegido no sale');

        $etiquetas = collect($componente->set('tipoGestionId', $tipoId)->set('resultadoId', $resultadoId)
            ->viewData('plantillasNota'))->pluck('etiqueta')->all();

        $this->assertContains('Pide llamada', $etiquetas);
    }

    public function test_una_plantilla_desactivada_no_sale(): void
    {
        [$proyecto, $casoId, $personaId] = $this->escenario();
        $id = $this->plantilla($proyecto, 'Buzón', 'Se dejó mensaje.', null);
        DB::table('plantillas_nota')->where('id', $id)->update(['activo' => false]);
        $gestor = $this->crearGestor($proyecto);
        $this->activarProyecto($proyecto);

        $this->assertCount(0, Livewire::actingAs($gestor)
            ->test(NuevaGestion::class, ['casoId' => $casoId, 'personaId' => $personaId, 'tipoCaso' => 'cobranza'])
            ->viewData('plantillasNota'));
    }

    /** Multi-tenancy (§12): las plantillas de un proyecto no salen en otro. */
    public function test_las_plantillas_no_cruzan_proyectos(): void
    {
        [$proyectoA] = $this->escenario();
        [$proyectoB, $casoB, $personaB] = $this->escenario();
        $this->plantilla($proyectoA, 'Sólo de A', 'Texto de A.', null);
        $gestor = $this->crearGestor($proyectoB);
        $this->activarProyecto($proyectoB);

        $this->assertCount(0, Livewire::actingAs($gestor)
            ->test(NuevaGestion::class, ['casoId' => $casoB, 'personaId' => $personaB, 'tipoCaso' => 'cobranza'])
            ->viewData('plantillasNota'));
    }

    public function test_el_configurador_crea_y_borra_plantillas(): void
    {
        [$proyecto] = $this->escenario();
        $admin = $this->crearAdminGlobal();

        Livewire::actingAs($admin)
            ->test(PasoResultados::class, ['proyecto' => ProyectoModel::find($proyecto->id)])
            ->set('plantilla.etiqueta', 'Pide llamada')
            ->set('plantilla.texto', 'El titular pide que se le llame después de las 6.')
            ->call('crearPlantilla')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('plantillas_nota', [
            'proyecto_id' => $proyecto->id, 'etiqueta' => 'Pide llamada',
        ]);
    }

    public function test_no_se_puede_colgar_una_plantilla_de_un_resultado_ajeno(): void
    {
        [$proyectoA] = $this->escenario();
        [, , , , $resultadoB] = $this->escenario();
        $admin = $this->crearAdminGlobal();

        Livewire::actingAs($admin)
            ->test(PasoResultados::class, ['proyecto' => ProyectoModel::find($proyectoA->id)])
            ->set('plantilla.etiqueta', 'X')
            ->set('plantilla.texto', 'Y')
            ->set('plantilla.resultado_id', $resultadoB)
            ->call('crearPlantilla')
            ->assertHasErrors('plantilla.resultado_id');
    }

    public function test_el_textarea_de_notas_crece_y_cuenta(): void
    {
        [$proyecto, $casoId, $personaId] = $this->escenario();
        $gestor = $this->crearGestor($proyecto);
        $this->activarProyecto($proyecto);

        $html = Livewire::actingAs($gestor)
            ->test(NuevaGestion::class, ['casoId' => $casoId, 'personaId' => $personaId, 'tipoCaso' => 'cobranza'])
            ->html();

        $this->assertStringContainsString('maxlength="2000"', $html);
        $this->assertStringContainsString('restantes', $html, 'el contador');
        $this->assertStringContainsString('crecer(', $html, 'el autosize');
        $this->assertStringNotContainsString('rows="2"', $html);
    }

    private function plantilla(stdClass $proyecto, string $etiqueta, string $texto, ?int $resultadoId): int
    {
        return (int) DB::table('plantillas_nota')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'resultado_id' => $resultadoId,
            'etiqueta' => $etiqueta,
            'texto' => $texto,
            'activo' => true,
            'orden' => 0,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
    }

    /** @return array{0: stdClass, 1: int, 2: int, 3: int, 4: int} */
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

        $tipoId = (int) DB::table('tipos_gestion')->insertGetId([
            'proyecto_id' => $proyecto->id, 'codigo' => 'LLAMADA', 'nombre' => 'Llamada',
            'activo' => true, 'orden' => 10, 'creada_en' => Carbon::now(), 'actualizada_en' => Carbon::now(),
        ]);

        $resultadoId = (int) DB::table('resultados')->insertGetId([
            'proyecto_id' => $proyecto->id, 'codigo' => 'CONTACTO', 'nombre' => 'Contacto',
            'activo' => true, 'orden' => 10,
            'es_contacto_efectivo' => true, 'requiere_compromiso' => false, 'requiere_causa' => false,
            'creada_en' => Carbon::now(), 'actualizada_en' => Carbon::now(),
        ]);

        return [$proyecto, $casoId, (int) $persona->id, $tipoId, $resultadoId];
    }
}
