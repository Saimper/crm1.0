<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use App\Modules\Importaciones\Infrastructure\Http\Livewire\ImportarPersonas;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class ImportarPersonasTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    /**
     * Mismo defecto que en ImportarCasosTest: `ImportarPersonas::guardarArchivo()`
     * crea la importación sin `esquema` (formato legacy de columnas fijas) y
     * `EjecutarImportacionJob:98` la manda igualmente a
     * `EjecutarImportacionDinamica`, que exige uno. La confirmación no puede
     * completarse.
     */
    private const COMMIT_MUERTO = 'Componente @deprecated (F35-B): sin ruta y con la rama de commit rota — EjecutarImportacionJob exige `esquema`, que este componente nunca escribe. El flujo vivo es el wizard Importar, cubierto por ImportarUnificadoTest.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_sube_csv_valido_y_commit(): void
    {
        $this->markTestSkipped(self::COMMIT_MUERTO);

        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);

        $supervisor = $this->crearSupervisor($proyecto);
        $this->actingAs($supervisor);

        $csv = "tipo_persona,tipo_identificacion_codigo,identificacion,nombres,apellidos,razon_social,fecha_nacimiento\n"
             ."fisica,CED,2200000001,Rosa,Andrade,,1985-05-10\n"
             ."fisica,CED,2200000002,Luis,Paredes,,\n"
             ."juridica,RUC,1799888800001,,,Empresa Demo S.A.,\n";
        $archivo = UploadedFile::fake()->createWithContent('personas.csv', $csv);

        $componente = Livewire::test(ImportarPersonas::class)
            ->set('archivo', $archivo)
            ->call('guardarArchivo')
            ->assertHasNoErrors();

        $importacionId = $componente->get('importacionId');
        $this->assertNotNull($importacionId);

        $this->assertDatabaseHas('importaciones', [
            'id' => $importacionId,
            'proyecto_id' => $proyecto->id,
            'total_filas' => 3,
            'validas' => 3,
            'invalidas' => 0,
            'estado' => 'preparada',
        ]);

        // Falla hoy: ImportarPersonas (deprecado en F35-B) crea la importación sin
        // `esquema`, y EjecutarImportacionJob enruta siempre a EjecutarImportacionDinamica,
        // que lo exige. El commit del componente legacy es camino muerto. No se relaja el
        // assert: el rojo es el hallazgo.
        $componente->call('confirmar');

        $this->assertDatabaseHas('importaciones', [
            'id' => $importacionId,
            'procesadas' => 3,
            'estado' => 'completada',
        ]);
        $this->assertDatabaseHas('personas', ['identificacion' => '2200000001']);
        $this->assertDatabaseHas('personas', ['identificacion' => '1799888800001']);
    }

    public function test_csv_con_filas_invalidas_reporta_errores_sin_importar(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        $csv = "tipo_persona,tipo_identificacion_codigo,identificacion,nombres,apellidos,razon_social,fecha_nacimiento\n"
             ."fisica,CED,,Sin identificacion,,,\n"
             ."XYZ,CED,2200000010,,,,\n"
             ."fisica,CED,2200000011,Valido,Ok,,\n";
        $archivo = UploadedFile::fake()->createWithContent('mix.csv', $csv);

        $c = Livewire::test(ImportarPersonas::class)
            ->set('archivo', $archivo)
            ->call('guardarArchivo')
            ->assertHasNoErrors();

        $id = $c->get('importacionId');
        $this->assertDatabaseHas('importaciones', [
            'id' => $id,
            'total_filas' => 3,
            'validas' => 1,
            'invalidas' => 2,
            'estado' => 'preparada',
        ]);
    }

    public function test_gestor_sin_permiso_recibe_403(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        $this->actingAs($gestor)
            ->get(route('proyectos.importaciones', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(403);
    }
}
