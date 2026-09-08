<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use App\Modules\Importaciones\Infrastructure\Http\Livewire\Importar;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * El wizard entero, cerrado por permiso y por pertenencia.
 *
 * La ruta pide `importaciones.crear`, pero cada paso del wizard es un POST
 * aparte a /livewire/update que no vuelve a pasar por ese middleware. `cancelar()`
 * no comprobaba nada y `CancelarImportacion` busca la fila con
 * `sinScopeProyecto()`: bastaba reapuntar `importacionId` para detener la carga
 * de otro mandante. Y `carteraId` sigue siendo elegible desde el cliente —es un
 * `wire:model` legítimo—, así que se comprueba que sea del proyecto activo.
 */
final class ImportarAutorizacionTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_importacion_id_no_es_reapuntable_desde_el_cliente(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(Importar::class)->set('importacionId', 12345);
    }

    public function test_gestor_no_puede_cancelar_una_importacion(): void
    {
        [$proyecto, $componente, $importacionId] = $this->wizardPreparado();

        $this->actingAs($this->crearGestor($proyecto));

        $componente->call('cancelar')->assertForbidden();

        $this->assertNotSame(
            'cancelada',
            (string) DB::table('importaciones')->where('id', $importacionId)->value('estado'),
            'El GESTOR no tiene importaciones.procesar: la importación no debe haberse cancelado.'
        );
    }

    public function test_gestor_no_puede_ejecutar_una_importacion(): void
    {
        [$proyecto, $componente, $importacionId] = $this->wizardPreparado();

        $this->actingAs($this->crearGestor($proyecto));

        $componente->call('ejecutar')->assertForbidden();

        $this->assertSame(
            'preparada',
            (string) DB::table('importaciones')->where('id', $importacionId)->value('estado'),
            'Sin importaciones.procesar la importación debe quedarse donde estaba.'
        );
    }

    public function test_supervisor_si_puede_cancelar_su_importacion(): void
    {
        [$proyecto, $componente, $importacionId] = $this->wizardPreparado();

        $this->actingAs($this->crearSupervisor($proyecto));

        $componente->call('cancelar')->assertHasNoErrors();

        $this->assertSame(
            'cancelada',
            (string) DB::table('importaciones')->where('id', $importacionId)->value('estado'),
            'El camino legítimo debe seguir vivo: el SUPERVISOR cancela lo suyo.'
        );
    }

    public function test_supervisor_si_puede_ejecutar_su_importacion(): void
    {
        [$proyecto, $componente, $importacionId] = $this->wizardPreparado();

        $this->actingAs($this->crearSupervisor($proyecto));

        $componente->call('ejecutar')->assertHasNoErrors();

        $this->assertSame(
            'completada',
            (string) DB::table('importaciones')->where('id', $importacionId)->value('estado')
        );
        $this->assertDatabaseHas('personas', [
            'proyecto_id' => $proyecto->id,
            'identificacion' => '1700000555',
        ]);
    }

    public function test_no_se_puede_subir_contra_la_cartera_de_otro_proyecto(): void
    {
        $mandante = $this->crearMandante();
        $proyectoA = $this->crearProyectoCobranza($mandante);
        $proyectoB = $this->crearProyectoCobranza($mandante);
        $carteraAjena = $this->crearCarteraEn($proyectoB, 'CART_B');

        $this->activarProyecto($proyectoA);
        $this->actingAs($this->crearSupervisor($proyectoA));

        $csv = "ced,nom\n100,Ana\n";

        Livewire::test(Importar::class)
            ->set('targetValor', TargetImportacion::CASO_COBRANZA->value)
            ->set('carteraId', (int) $carteraAjena->id)
            ->set('archivo', UploadedFile::fake()->createWithContent('ajena.csv', $csv))
            ->call('subirArchivo')
            ->assertNotFound();

        $this->assertSame(
            0,
            (int) DB::table('importaciones')->count(),
            'Ni siquiera debe haber llegado a inferir el esquema.'
        );
    }

    public function test_confirmar_mapeo_con_cartera_ajena_no_crea_la_importacion(): void
    {
        $mandante = $this->crearMandante();
        $proyectoA = $this->crearProyectoCobranza($mandante);
        $proyectoB = $this->crearProyectoCobranza($mandante);
        $carteraPropia = $this->crearCarteraEn($proyectoA, 'CART_A');
        $carteraAjena = $this->crearCarteraEn($proyectoB, 'CART_B');
        $this->crearEstadoCasoEn($proyectoA, 'ABIERTO');

        $this->activarProyecto($proyectoA);
        $this->actingAs($this->crearAdminGlobal());

        $componente = Livewire::test(Importar::class)
            ->set('targetValor', TargetImportacion::CASO_COBRANZA->value)
            ->set('carteraId', (int) $carteraPropia->id)
            ->set('archivo', UploadedFile::fake()->createWithContent('mix.csv', $this->csvCobranza()))
            ->call('subirArchivo')
            ->assertSet('paso', 2);

        // Reapuntar la cartera entre el paso 2 y el 3 era lo que dejaba colgar
        // casos del proyecto activo en la cartera de otro.
        $componente->set('carteraId', (int) $carteraAjena->id)
            ->call('confirmarMapeo')
            ->assertNotFound();

        $this->assertSame(0, (int) DB::table('importaciones')->count());
    }

    /**
     * Deja el wizard en el paso 3 (importación `preparada`).
     *
     * Lo prepara un ADMIN_GLOBAL porque el mapeo crea campos personalizados y
     * eso pide `campos.definir`, que el SUPERVISOR no tiene (§7, F23). Lo que
     * se prueba después —quién puede ejecutar y cancelar lo ya preparado— es
     * otro permiso, y cada test declara con quién actúa.
     *
     * @return array{0: stdClass, 1: Testable, 2: int}
     */
    private function wizardPreparado(): array
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto, 'CART_T');
        $this->crearEstadoCasoEn($proyecto, 'ABIERTO');

        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearAdminGlobal());

        $componente = Livewire::test(Importar::class)
            ->set('targetValor', TargetImportacion::CASO_COBRANZA->value)
            ->set('carteraId', (int) $cartera->id)
            ->set('archivo', UploadedFile::fake()->createWithContent('ok.csv', $this->csvCobranza()))
            ->call('subirArchivo')
            ->assertHasNoErrors()
            ->assertSet('paso', 2);

        $componente->call('marcarComoIdentificador', 'Identificacion')
            ->call('marcarComoIdentificadorCaso', 'NumeroPrestamo')
            ->call('confirmarMapeo')
            ->assertHasNoErrors()
            ->assertSet('paso', 3);

        $importacionId = (int) $componente->get('importacionId');
        $this->assertGreaterThan(0, $importacionId);

        return [$proyecto, $componente, $importacionId];
    }

    private function csvCobranza(): string
    {
        return "Cartera,TipoIdentificacion,Identificacion,Nombres,Apellidos,NumeroPrestamo,Moneda,MO,SC,ST,FD,FV,Cu\n"
            ."CART_T,CED,1700000555,Ana,Diaz,PR-AUTH-1,USD,1000,800,800,2025-10-01,2026-10-01,12\n";
    }
}
