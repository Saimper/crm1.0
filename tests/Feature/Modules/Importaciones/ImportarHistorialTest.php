<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use App\Modules\Importaciones\Infrastructure\Http\Livewire\Importar;
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
 * El historial sólo decía «fallida» y el motivo se enseñaba únicamente en el
 * paso 4 de la sesión en curso: cerrar la pestaña era perderlo. Ahora el
 * motivo está en la fila, y «Ver» reabre el paso 4 de cualquier importación
 * pasada del proyecto.
 */
final class ImportarHistorialTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_el_historial_ensena_el_motivo_de_una_importacion_fallida(): void
    {
        $proyecto = $this->contexto();
        $this->importacionEn($proyecto, [
            'estado' => 'fallida',
            'error_global' => 'La base de datos rechazó el lote (SQLSTATE 23000, error 1062). Referencia 0badf00d.',
        ]);

        Livewire::test(Importar::class)
            ->assertSee('Referencia 0badf00d');
    }

    public function test_ver_abre_el_paso_4_de_una_importacion_pasada_con_su_descarga_de_rechazadas(): void
    {
        $proyecto = $this->contexto();
        $importacionId = $this->importacionEn($proyecto, [
            'nombre_archivo' => 'cartera_agosto.xlsx',
            'estado' => 'completada',
            'total_filas' => 10,
            'procesadas' => 7,
            'invalidas' => 2,
            'duplicadas' => 1,
        ]);
        $publicId = (string) DB::table('importaciones')->where('id', $importacionId)->value('public_id');

        Livewire::test(Importar::class)
            ->call('verImportacion', $importacionId)
            ->assertHasNoErrors()
            ->assertSet('paso', 4)
            ->assertSet('importacionId', $importacionId)
            ->assertSee('cartera_agosto.xlsx')
            ->assertSee(__('importaciones.btn_download_rejected', ['count' => 3]))
            ->assertSee(route('proyectos.importaciones.rechazadas', ['proyecto_id' => $proyecto->id, 'importacion' => $publicId]), escape: false);
    }

    /**
     * Pasada la retención, el contenido del archivo se depura y la descarga de
     * rechazadas saldría con las columnas en blanco. La pantalla deja de
     * ofrecerla; el 410 del controlador es sólo para la URL guardada.
     */
    public function test_una_importacion_ya_depurada_no_ofrece_la_descarga(): void
    {
        $proyecto = $this->contexto();
        $importacionId = $this->importacionEn($proyecto, [
            'estado' => 'completada',
            'total_filas' => 10,
            'procesadas' => 7,
            'invalidas' => 3,
            'payload_purgado_en' => Carbon::now(),
        ]);
        $publicId = (string) DB::table('importaciones')->where('id', $importacionId)->value('public_id');

        Livewire::test(Importar::class)
            ->call('verImportacion', $importacionId)
            ->assertSet('paso', 4)
            ->assertDontSee(route('proyectos.importaciones.rechazadas', ['proyecto_id' => $proyecto->id, 'importacion' => $publicId]), escape: false);
    }

    public function test_una_importacion_sin_rechazadas_no_ofrece_la_descarga(): void
    {
        $proyecto = $this->contexto();
        $importacionId = $this->importacionEn($proyecto, ['estado' => 'completada', 'total_filas' => 3, 'procesadas' => 3]);

        Livewire::test(Importar::class)
            ->call('verImportacion', $importacionId)
            ->assertSet('paso', 4)
            ->assertDontSee(__('importaciones.btn_download_rejected', ['count' => 0]));
    }

    /** Multi-tenancy (§12): el id del historial de otro proyecto no abre nada, y responde como si no existiera. */
    public function test_ver_una_importacion_de_otro_proyecto_responde_404(): void
    {
        $proyectoA = $this->contexto();
        $proyectoB = $this->crearProyectoCobranza();
        $ajena = $this->importacionEn($proyectoB, ['estado' => 'completada']);

        Livewire::test(Importar::class)
            ->call('verImportacion', $ajena)
            ->assertNotFound();

        $this->assertNotSame($proyectoA->id, $proyectoB->id);
    }

    public function test_un_gestor_no_puede_abrir_importaciones_pasadas(): void
    {
        $proyecto = $this->contexto();
        $importacionId = $this->importacionEn($proyecto, ['estado' => 'completada']);

        // El componente se monta con quien sí puede; el commit llega con otro
        // usuario, que es el caso que cubre la guarda de la acción.
        $componente = Livewire::test(Importar::class);
        $this->actingAs($this->crearGestor($proyecto));

        $componente->call('verImportacion', $importacionId)->assertForbidden();
    }

    private function contexto(): stdClass
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        return $proyecto;
    }

    /** @param array<string, mixed> $atributos */
    private function importacionEn(stdClass $proyecto, array $atributos): int
    {
        return (int) DB::table('importaciones')->insertGetId(array_merge([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'tipo_entidad' => 'caso_cobranza',
            'modo' => 'upsert',
            'estado' => 'completada',
            'usuario_id' => $this->crearSupervisor($proyecto)->id,
            'nombre_archivo' => 'archivo.csv',
            'total_filas' => 1,
        ], $atributos));
    }
}
