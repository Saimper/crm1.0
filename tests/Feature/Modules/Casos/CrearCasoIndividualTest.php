<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Casos;

use App\Modules\Casos\Infrastructure\Http\Livewire\CrearCasoIndividual;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * F35-D: form Crear Caso minimal — solo cartera + persona + ID único + prioridad.
 * Los campos CTI hardcoded fueron eliminados; el admin del proyecto define
 * qué campos pedir vía Campos Personalizados §7. Estos tests reflejan el form mínimo.
 */
final class CrearCasoIndividualTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_crea_caso_cobranza_minimal_asigna_primer_estado(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);

        $estadoPrimero = DB::table('estados_caso')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'ABIERTO',
            'nombre' => 'Abierto',
            'activo' => true,
            'es_terminal' => false,
            'orden' => 1,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
        DB::table('estados_caso')->insert([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'EN_GESTION',
            'nombre' => 'En gestión',
            'activo' => true,
            'es_terminal' => false,
            'orden' => 2,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        $this->actuarComoSupervisor($proyecto);

        Livewire::test(CrearCasoIndividual::class, ['personaPublicId' => $persona->public_id])
            ->set('carteraId', (string) $cartera->id)
            ->set('idUnico', 'CCI-PRST-0001')
            ->call('guardar')
            ->assertHasNoErrors();

        $caso = DB::table('casos')
            ->where('proyecto_id', $proyecto->id)
            ->where('persona_id', $persona->id)
            ->first();
        $this->assertNotNull($caso);
        $this->assertSame((int) $estadoPrimero, (int) $caso->estado_caso_id);

        $this->assertDatabaseHas('casos_cobranza', [
            'numero_prestamo' => 'CCI-PRST-0001',
            'monto_original' => null,
            'saldo_capital' => null,
            'fecha_desembolso' => null,
        ]);
    }

    public function test_crea_caso_cx_minimal(): void
    {
        $proyecto = $this->crearProyectoCx();
        $cartera = $this->crearCarteraEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);
        $this->crearEstadoCasoEn($proyecto, 'ABIERTO');

        $this->actuarComoSupervisor($proyecto);

        Livewire::test(CrearCasoIndividual::class, ['personaPublicId' => $persona->public_id])
            ->set('carteraId', (string) $cartera->id)
            ->set('idUnico', 'CCI-TKT-0001')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('casos_ticket_cx', [
            'codigo_ticket' => 'CCI-TKT-0001',
            'asunto' => null,
            'fecha_reporte' => null,
        ]);
    }

    public function test_crea_caso_venta_minimal(): void
    {
        $proyecto = $this->crearProyectoVenta();
        $cartera = $this->crearCarteraEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);
        $this->crearEstadoCasoEn($proyecto, 'ABIERTO');

        $this->actuarComoSupervisor($proyecto);

        Livewire::test(CrearCasoIndividual::class, ['personaPublicId' => $persona->public_id])
            ->set('carteraId', (string) $cartera->id)
            ->set('idUnico', 'CCI-LD-0001')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('casos_lead_venta', [
            'codigo_lead' => 'CCI-LD-0001',
            'fecha_primer_contacto' => null,
        ]);
    }

    public function test_crea_caso_servicio_minimal(): void
    {
        $proyecto = $this->crearProyectoServicio();
        $cartera = $this->crearCarteraEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);
        $this->crearEstadoCasoEn($proyecto, 'ABIERTO');

        $this->actuarComoSupervisor($proyecto);

        Livewire::test(CrearCasoIndividual::class, ['personaPublicId' => $persona->public_id])
            ->set('carteraId', (string) $cartera->id)
            ->set('idUnico', 'CCI-SV-0001')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('casos_servicio', [
            'codigo_servicio' => 'CCI-SV-0001',
            'fecha_solicitud' => null,
        ]);
    }

    public function test_falla_si_proyecto_sin_estados_activos(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);

        $this->actuarComoSupervisor($proyecto);

        Livewire::test(CrearCasoIndividual::class, ['personaPublicId' => $persona->public_id])
            ->set('carteraId', (string) $cartera->id)
            ->set('idUnico', 'X')
            ->call('guardar')
            ->assertHasErrors(['general']);

        $this->assertSame(
            0,
            (int) DB::table('casos')->where('proyecto_id', $proyecto->id)->count()
        );
    }

    public function test_form_no_renderiza_campos_cti_hardcoded(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $persona = $this->crearPersonaEn($proyecto);
        $this->crearEstadoCasoEn($proyecto, 'ABIERTO');

        $this->actuarComoSupervisor($proyecto);

        $resp = $this->get(route('proyectos.casos.crear', [
            'proyecto_id' => $proyecto->id,
            'persona' => $persona->public_id,
        ]));

        $resp->assertOk();
        $resp->assertDontSee('Estado inicial');
        $resp->assertDontSee('wire:model="estadoCasoId"', false);
        // Campos CTI hardcoded eliminados — admin del proyecto los define vía Campos Personalizados.
        $resp->assertDontSee('Datos del préstamo');
        $resp->assertDontSee('Saldo capital');
        $resp->assertDontSee('Fecha desembolso');
    }

    public function test_form_id_unico_etiqueta_cambia_segun_tipo(): void
    {
        foreach (['cobranza' => 'Número de préstamo', 'cx' => 'Código de ticket', 'venta' => 'Código de lead', 'servicio' => 'Código de servicio'] as $tipo => $etiqueta) {
            $proyecto = $this->crearProyecto($tipo);
            $persona = $this->crearPersonaEn($proyecto);
            $this->crearEstadoCasoEn($proyecto, 'ABIERTO');
            $this->actuarComoSupervisor($proyecto);

            $resp = $this->get(route('proyectos.casos.crear', [
                'proyecto_id' => $proyecto->id,
                'persona' => $persona->public_id,
            ]));
            $resp->assertSee($etiqueta);
        }
    }

    public function test_persona_invalida_no_crea_caso(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->crearEstadoCasoEn($proyecto, 'ABIERTO');
        $this->actuarComoSupervisor($proyecto);

        $ulidFalso = (string) Str::ulid();

        Livewire::test(CrearCasoIndividual::class, ['personaPublicId' => $ulidFalso])
            ->call('guardar')
            ->assertHasErrors();
    }

    public function test_gestor_recibe_403_en_ruta(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        $this->actingAs($gestor)
            ->get(route('proyectos.casos.crear', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(403);
    }

    public function test_supervisor_accede_ruta(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->crearEstadoCasoEn($proyecto, 'ABIERTO');
        $supervisor = $this->crearSupervisor($proyecto);

        $this->actingAs($supervisor)
            ->get(route('proyectos.casos.crear', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(200);
    }

    /**
     * El `can:casos.crear` de la ruta no protege el commit: `guardar` es un POST
     * aparte a /livewire/update. El GESTOR entra a la Vista de Trabajo con
     * `casos.ver`, y desde ahí podía montar este componente y llamar al método.
     */
    public function test_gestor_no_puede_crear_el_caso_aunque_monte_el_componente(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);
        $this->crearEstadoCasoEn($proyecto, 'ABIERTO');

        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearGestor($proyecto));

        Livewire::test(CrearCasoIndividual::class, ['personaPublicId' => $persona->public_id])
            ->set('carteraId', (string) $cartera->id)
            ->set('idUnico', 'CCI-GESTOR-0001')
            ->call('guardar')
            ->assertForbidden();

        $this->assertSame(
            0,
            (int) DB::table('casos')->where('proyecto_id', $proyecto->id)->count(),
            'El GESTOR no tiene casos.crear: no debe haber quedado ningún caso.'
        );
    }

    public function test_auditor_no_puede_crear_el_caso(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);
        $this->crearEstadoCasoEn($proyecto, 'ABIERTO');

        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearAuditor($proyecto));

        Livewire::test(CrearCasoIndividual::class, ['personaPublicId' => $persona->public_id])
            ->set('carteraId', (string) $cartera->id)
            ->set('idUnico', 'CCI-AUDITOR-0001')
            ->call('guardar')
            ->assertForbidden();

        $this->assertSame(0, (int) DB::table('casos')->where('proyecto_id', $proyecto->id)->count());
    }

    /** El camino legítimo, en el mismo escenario que los dos anteriores. */
    public function test_supervisor_si_puede_crear_el_caso(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);
        $this->crearEstadoCasoEn($proyecto, 'ABIERTO');

        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        Livewire::test(CrearCasoIndividual::class, ['personaPublicId' => $persona->public_id])
            ->set('carteraId', (string) $cartera->id)
            ->set('idUnico', 'CCI-SUPER-0001')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('casos_cobranza', ['numero_prestamo' => 'CCI-SUPER-0001']);
        $this->assertSame(1, (int) DB::table('casos')->where('proyecto_id', $proyecto->id)->count());
    }

    /**
     * La cartera la elige el cliente en un `<select>`, y ni el UseCase ni la FK
     * comprueban de qué proyecto es: el supervisor arrastraba su permiso al
     * proyecto ajeno y dejaba el caso colgado de la cartera del otro mandante.
     */
    public function test_no_se_puede_crear_el_caso_con_la_cartera_de_otro_mandante(): void
    {
        $propio = $this->crearProyectoCobranza();
        $ajeno = $this->crearProyectoCobranza();
        $carteraAjena = $this->crearCarteraEn($ajeno);

        $persona = $this->crearPersonaEn($propio);
        $this->crearEstadoCasoEn($propio, 'ABIERTO');

        $this->activarProyecto($propio);
        $this->actingAs($this->crearSupervisor($propio));

        Livewire::test(CrearCasoIndividual::class, ['personaPublicId' => $persona->public_id])
            ->set('carteraId', (string) $carteraAjena->id)
            ->set('idUnico', 'CCI-FUGA-0001')
            ->call('guardar')
            ->assertNotFound();

        $this->assertSame(
            0,
            (int) DB::table('casos')->where('cartera_id', $carteraAjena->id)->count(),
            'FUGA: se creó un caso apuntando a la cartera de otro mandante.'
        );
    }

    /** La persona de otro proyecto no se resuelve: `mount` la busca scoped. */
    public function test_no_se_puede_crear_el_caso_para_la_persona_de_otro_mandante(): void
    {
        $propio = $this->crearProyectoCobranza();
        $ajeno = $this->crearProyectoCobranza();
        $personaAjena = $this->crearPersonaEn($ajeno);
        $cartera = $this->crearCarteraEn($propio);
        $this->crearEstadoCasoEn($propio, 'ABIERTO');

        $this->activarProyecto($propio);
        $this->actingAs($this->crearSupervisor($propio));

        Livewire::test(CrearCasoIndividual::class, ['personaPublicId' => $personaAjena->public_id])
            ->set('carteraId', (string) $cartera->id)
            ->set('idUnico', 'CCI-FUGA-0002')
            ->call('guardar')
            ->assertHasErrors(['personaPublicId']);

        $this->assertSame(0, (int) DB::table('casos')->where('persona_id', $personaAjena->id)->count());
    }

    /** `#[Locked]`: el id de la persona no se reapunta desde la consola. */
    public function test_el_id_de_la_persona_no_se_puede_reapuntar_desde_el_cliente(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $persona = $this->crearPersonaEn($proyecto);
        $otra = $this->crearPersonaEn($proyecto);
        $this->crearEstadoCasoEn($proyecto, 'ABIERTO');

        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(CrearCasoIndividual::class, ['personaPublicId' => $persona->public_id])
            ->set('personaId', (int) $otra->id);
    }

    /** `#[Locked]`: el tipo decide a qué UseCase se despacha (§13.13). */
    public function test_el_tipo_de_operacion_no_se_puede_reapuntar_desde_el_cliente(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $persona = $this->crearPersonaEn($proyecto);
        $this->crearEstadoCasoEn($proyecto, 'ABIERTO');

        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(CrearCasoIndividual::class, ['personaPublicId' => $persona->public_id])
            ->set('tipoOperacion', 'venta');
    }

    private function actuarComoSupervisor(stdClass $proyecto): void
    {
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));
    }
}
