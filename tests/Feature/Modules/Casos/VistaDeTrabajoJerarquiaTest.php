<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Casos;

use App\Modules\Casos\Infrastructure\Http\Livewire\NuevaGestion;
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
 * La Vista de Trabajo separa capturar una gestión de mirar los datos del caso.
 *
 * Antes el formulario de gestión mezclaba sus 6 campos con los 34 del caso
 * —98 en el proyecto grande— en una rejilla de tres columnas sin agrupar, y el
 * botón de guardar quedaba a más de 2.500px de scroll del primer campo. Los
 * campos del caso además se escribían desde ahí, que era una segunda superficie
 * de escritura sobre el mismo dato y por donde se borraban valores.
 */
final class VistaDeTrabajoJerarquiaTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_el_formulario_de_gestion_ya_no_pinta_los_campos_del_caso(): void
    {
        [$proyecto, $casoId, $personaId] = $this->escenario();
        $gestor = $this->crearGestor($proyecto);
        $this->activarProyecto($proyecto);

        $html = Livewire::actingAs($gestor)
            ->test(NuevaGestion::class, ['casoId' => $casoId, 'personaId' => $personaId, 'tipoCaso' => 'cobranza'])
            ->html();

        $this->assertStringNotContainsString('valoresCamposCaso', $html);
        $this->assertStringNotContainsString('Saldo total', $html);
    }

    /** Registrar una gestión no puede tocar los valores del caso. */
    public function test_registrar_una_gestion_no_escribe_campos_del_caso(): void
    {
        [$proyecto, $casoId, $personaId, $campoId] = $this->escenario();
        $gestor = $this->crearGestor($proyecto);
        $this->activarProyecto($proyecto);

        Livewire::actingAs($gestor)
            ->test(NuevaGestion::class, ['casoId' => $casoId, 'personaId' => $personaId, 'tipoCaso' => 'cobranza'])
            ->set('canalId', (int) DB::table('canales')->value('id'))
            ->set('tipoGestionId', $this->catalogo($proyecto, 'tipos_gestion'))
            ->set('resultadoId', $this->catalogo($proyecto, 'resultados'))
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('gestiones', 1);
        $this->assertSame('390.00', DB::table('valores_campo_personalizado')
            ->where('campo_personalizado_id', $campoId)->where('entidad_id', $casoId)
            ->value('valor_texto_corto'));
    }

    public function test_la_barra_de_guardar_va_pegada_abajo(): void
    {
        [$proyecto, $casoId, $personaId] = $this->escenario();
        $gestor = $this->crearGestor($proyecto);
        $this->activarProyecto($proyecto);

        $html = Livewire::actingAs($gestor)
            ->test(NuevaGestion::class, ['casoId' => $casoId, 'personaId' => $personaId, 'tipoCaso' => 'cobranza'])
            ->html();

        $this->assertStringContainsString('sticky bottom-0', $html);
    }

    /**
     * El atajo iba en `.window`, así que también disparaba mientras se escribía
     * en el panel de entidades vinculadas, que es otro componente vivo en la
     * misma pantalla. Y no capturaba Cmd+Enter en macOS.
     */
    public function test_el_atajo_esta_acotado_al_formulario_y_cubre_macos(): void
    {
        [$proyecto, $casoId, $personaId] = $this->escenario();
        $gestor = $this->crearGestor($proyecto);
        $this->activarProyecto($proyecto);

        $html = Livewire::actingAs($gestor)
            ->test(NuevaGestion::class, ['casoId' => $casoId, 'personaId' => $personaId, 'tipoCaso' => 'cobranza'])
            ->html();

        $this->assertStringNotContainsString('keydown.ctrl.enter.window', $html);
        $this->assertStringContainsString('keydown.ctrl.enter', $html);
        $this->assertStringContainsString('keydown.meta.enter', $html);
    }

    public function test_la_vista_de_trabajo_agrupa_los_campos_del_caso_en_lectura(): void
    {
        [$proyecto, $casoId, $personaId, , $personaPublicId, $casoPublicId] = $this->escenario();
        $gestor = $this->crearGestor($proyecto);

        $html = $this->actingAs($gestor)
            ->get("/proyectos/{$proyecto->id}/trabajo/{$personaPublicId}/{$casoPublicId}")
            ->assertOk()
            ->getContent();

        $this->assertIsString($html);
        $this->assertStringContainsString('Deuda', $html, 'el nombre del grupo');
        $this->assertStringContainsString('390.00', $html, 'el valor, en lectura');
        $this->assertStringContainsString('<details', $html, 'plegable');
        $this->assertStringNotContainsString('wire:model="valoresCamposCaso.saldo_total"', $html);
    }

    /** Un campo marcado como no visible no se asoma a la Vista de Trabajo. */
    public function test_un_campo_oculto_no_aparece_en_la_vista_de_trabajo(): void
    {
        [$proyecto, $casoId, $personaId, $campoId, $personaPublicId, $casoPublicId] = $this->escenario();
        DB::table('campos_personalizados')->where('id', $campoId)->update(['visible_en_gestion' => false]);
        $gestor = $this->crearGestor($proyecto);

        $html = $this->actingAs($gestor)
            ->get("/proyectos/{$proyecto->id}/trabajo/{$personaPublicId}/{$casoPublicId}")
            ->assertOk()
            ->getContent();

        $this->assertIsString($html);
        $this->assertStringNotContainsString('390.00', $html);
    }

    /** @return array{0: stdClass, 1: int, 2: int, 3: int, 4: string, 5: string} */
    private function escenario(): array
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto, 'ABIERTO');
        $persona = $this->crearPersonaEn($proyecto);

        $casoPublicId = (string) Str::ulid();
        $casoId = (int) DB::table('casos')->insertGetId([
            'public_id' => $casoPublicId,
            'proyecto_id' => $proyecto->id,
            'cartera_id' => $cartera->id,
            'persona_id' => $persona->id,
            'tipo_caso' => 'cobranza',
            'estado_caso_id' => $estado->id,
            'fecha_ingreso' => Carbon::today(),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        $grupoId = (int) DB::table('grupos_campo')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'DEUDA',
            'nombre' => 'Deuda',
            'activo' => true,
            'orden' => 10,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        $campoId = (int) DB::table('campos_personalizados')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'ambito' => 'caso',
            'ambito_id' => $cartera->id,
            'grupo_campo_id' => $grupoId,
            'codigo' => 'saldo_total',
            'etiqueta' => 'Saldo total',
            'tipo' => 'texto_corto',
            'obligatorio' => false,
            'activo' => true,
            'visible_en_gestion' => true,
            'orden' => 10,
            'reglas' => json_encode([]),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        DB::table('valores_campo_personalizado')->insert([
            'campo_personalizado_id' => $campoId,
            'entidad_id' => $casoId,
            'valor_texto_corto' => '390.00',
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        return [$proyecto, $casoId, (int) $persona->id, $campoId, (string) $persona->public_id, $casoPublicId];
    }

    private function catalogo(stdClass $proyecto, string $tabla): int
    {
        $id = DB::table($tabla)->where('proyecto_id', $proyecto->id)->value('id');

        return $id !== null ? (int) $id : (int) DB::table($tabla)->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => strtoupper(Str::random(8)),
            'nombre' => 'Catálogo de prueba',
            'activo' => true,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
    }
}
