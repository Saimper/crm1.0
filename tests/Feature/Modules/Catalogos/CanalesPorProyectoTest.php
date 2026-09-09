<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Catalogos;

use App\Modules\Casos\Infrastructure\Http\Livewire\NuevaGestion;
use App\Modules\Catalogos\Application\Listeners\SembrarCanalesDelProyecto;
use App\Modules\Tenancy\Domain\Events\ProyectoCreado;
use App\Modules\Tenancy\Domain\ValueObjects\TipoOperacion;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\PasoTiposGestion;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Cada proyecto elige qué canales usa, en qué orden y con qué nombre.
 *
 * `canales` sigue siendo el catálogo global que declara §8 y `canal_proyecto`
 * dice qué hace cada proyecto con él. De las tres formas de bajarlo a
 * por-proyecto es la única que no toca `gestiones.canal_id`: las gestiones ya
 * registradas siguen apuntando a los mismos ids y los joins de reportes y
 * exportaciones siguen leyendo `canales.nombre` sin enterarse.
 */
final class CanalesPorProyectoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /**
     * Sin esto un proyecto nuevo nace sin canales y no se puede registrar una
     * gestión, porque la cascada empieza ahí. Se prueba el listener, no el
     * helper de escenario, que inserta sin disparar el evento.
     */
    public function test_un_proyecto_nuevo_nace_con_todos_los_canales_globales(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        DB::table('canal_proyecto')->where('proyecto_id', $proyecto->id)->delete();

        (new SembrarCanalesDelProyecto)->handle($this->eventoDeCreacion($proyecto));

        $globales = DB::table('canales')->where('activo', true)->count();

        $this->assertSame($globales, DB::table('canal_proyecto')
            ->where('proyecto_id', $proyecto->id)->where('activo', true)->count());
    }

    public function test_el_listener_no_duplica_si_el_proyecto_ya_tiene_canales(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $antes = DB::table('canal_proyecto')->where('proyecto_id', $proyecto->id)->count();

        (new SembrarCanalesDelProyecto)->handle($this->eventoDeCreacion($proyecto));

        $this->assertSame($antes, DB::table('canal_proyecto')->where('proyecto_id', $proyecto->id)->count());
    }

    public function test_un_canal_desactivado_desaparece_del_formulario_de_gestion(): void
    {
        [$proyecto, $casoId, $personaId] = $this->escenario();
        $gestor = $this->crearGestor($proyecto);
        $this->activarProyecto($proyecto);

        $telefono = (int) DB::table('canales')->where('codigo', 'TELEFONO')->value('id');
        DB::table('canal_proyecto')->where('proyecto_id', $proyecto->id)->where('canal_id', $telefono)
            ->update(['activo' => false]);

        $ids = collect(Livewire::actingAs($gestor)
            ->test(NuevaGestion::class, ['casoId' => $casoId, 'personaId' => $personaId, 'tipoCaso' => 'cobranza'])
            ->viewData('canales'))->pluck('id')->all();

        $this->assertNotContains($telefono, $ids);
    }

    public function test_el_nombre_del_proyecto_gana_sobre_el_del_catalogo(): void
    {
        [$proyecto, $casoId, $personaId] = $this->escenario();
        $gestor = $this->crearGestor($proyecto);
        $this->activarProyecto($proyecto);

        $telefono = (int) DB::table('canales')->where('codigo', 'TELEFONO')->value('id');
        DB::table('canal_proyecto')->where('proyecto_id', $proyecto->id)->where('canal_id', $telefono)
            ->update(['etiqueta' => 'Llamada saliente']);

        $nombres = collect(Livewire::actingAs($gestor)
            ->test(NuevaGestion::class, ['casoId' => $casoId, 'personaId' => $personaId, 'tipoCaso' => 'cobranza'])
            ->viewData('canales'))->pluck('nombre')->all();

        $this->assertContains('Llamada saliente', $nombres);
        $this->assertNotContains('Teléfono', $nombres);
        $this->assertSame('Teléfono', DB::table('canales')->where('id', $telefono)->value('nombre'),
            'el catálogo global no se toca');
    }

    /**
     * La validación de `canalId` era `required|integer` a secas: ni exists, ni
     * activo, ni proyecto. Con canales por proyecto eso deja de ser un detalle.
     */
    public function test_no_se_puede_guardar_con_un_canal_que_el_proyecto_no_usa(): void
    {
        [$proyecto, $casoId, $personaId] = $this->escenario();
        $gestor = $this->crearGestor($proyecto);
        $this->activarProyecto($proyecto);

        $telefono = (int) DB::table('canales')->where('codigo', 'TELEFONO')->value('id');
        DB::table('canal_proyecto')->where('proyecto_id', $proyecto->id)->where('canal_id', $telefono)
            ->update(['activo' => false]);

        Livewire::actingAs($gestor)
            ->test(NuevaGestion::class, ['casoId' => $casoId, 'personaId' => $personaId, 'tipoCaso' => 'cobranza'])
            ->set('canalId', $telefono)
            ->set('tipoGestionId', $this->catalogo($proyecto, 'tipos_gestion'))
            ->set('resultadoId', $this->catalogo($proyecto, 'resultados'))
            ->call('guardar')
            ->assertHasErrors('canalId');

        $this->assertDatabaseCount('gestiones', 0);
    }

    /** Multi-tenancy (§12): lo que un proyecto configura no alcanza a otro. */
    public function test_desactivar_un_canal_en_un_proyecto_no_lo_desactiva_en_otro(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCobranza();
        $telefono = (int) DB::table('canales')->where('codigo', 'TELEFONO')->value('id');

        $admin = $this->crearAdminGlobal();
        Livewire::actingAs($admin)
            ->test(PasoTiposGestion::class, ['proyecto' => ProyectoModel::find($proyectoA->id)])
            ->call('alternarCanal', $telefono);

        $this->assertFalse((bool) DB::table('canal_proyecto')
            ->where('proyecto_id', $proyectoA->id)->where('canal_id', $telefono)->value('activo'));
        $this->assertTrue((bool) DB::table('canal_proyecto')
            ->where('proyecto_id', $proyectoB->id)->where('canal_id', $telefono)->value('activo'));
    }

    public function test_las_gestiones_ya_registradas_siguen_apuntando_al_catalogo_global(): void
    {
        [$proyecto, $casoId, $personaId] = $this->escenario();
        $gestor = $this->crearGestor($proyecto);
        $this->activarProyecto($proyecto);

        $canalId = (int) DB::table('canales')->value('id');

        Livewire::actingAs($gestor)
            ->test(NuevaGestion::class, ['casoId' => $casoId, 'personaId' => $personaId, 'tipoCaso' => 'cobranza'])
            ->set('canalId', $canalId)
            ->set('tipoGestionId', $this->catalogo($proyecto, 'tipos_gestion'))
            ->set('resultadoId', $this->catalogo($proyecto, 'resultados'))
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('gestiones', ['caso_id' => $casoId, 'canal_id' => $canalId]);
    }

    private function eventoDeCreacion(stdClass $proyecto): ProyectoCreado
    {
        return new ProyectoCreado(
            proyectoId: (int) $proyecto->id,
            publicId: (string) $proyecto->public_id,
            mandanteId: (int) $proyecto->mandante_id,
            tipoOperacion: TipoOperacion::from((string) $proyecto->tipo_operacion),
            creadaEn: new DateTimeImmutable,
        );
    }

    /** @return array{0: stdClass, 1: int, 2: int} */
    private function escenario(): array
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto, 'ABIERTO');
        $persona = $this->crearPersonaEn($proyecto);

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

        return [$proyecto, $casoId, (int) $persona->id];
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
