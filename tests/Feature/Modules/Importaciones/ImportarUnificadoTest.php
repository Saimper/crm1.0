<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use App\Models\User;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use App\Modules\Importaciones\Infrastructure\Http\Livewire\Importar;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * F35-C — wizard unificado con mapeo libre + creación automática de persona.
 *
 * El CSV/XLSX trae datos de persona + caso en mismas filas. Si la persona no
 * existe en el proyecto, se crea durante el commit. Si existe, se reutiliza.
 * Ya no hay target Persona standalone — solo el caso del tipo de proyecto.
 *
 * API actualizada (F35-E): $mapeo → $columnas + actualizarAccionColumna() /
 * marcarComoIdentificador(). Procesamiento via ejecutar() (síncrono).
 */
final class ImportarUnificadoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_pantalla_solo_muestra_caso_del_tipo_proyecto(): void
    {
        [$proyecto] = $this->contextoCobranza();

        $componente = Livewire::test(Importar::class);
        $disponibles = $componente->viewData('targetsDisponibles');

        $this->assertSame([TargetImportacion::CASO_COBRANZA], $disponibles, 'No debe ofrecer target persona standalone');
    }

    public function test_subir_csv_con_columnas_arbitrarias_no_falla(): void
    {
        [$proyecto] = $this->contextoCobranza();
        $cartera = $this->crearCarteraEn($proyecto, 'CART_T');

        $csv = "ced,nom,ape\n100,Ana,Diaz\n200,Luis,Paz\n";
        $archivo = UploadedFile::fake()->createWithContent('libre.csv', $csv);

        Livewire::test(Importar::class)
            ->set('targetValor', TargetImportacion::CASO_COBRANZA->value)
            ->set('carteraId', (int) $cartera->id)
            ->set('archivo', $archivo)
            ->call('subirArchivo')
            ->assertHasNoErrors()
            ->assertSet('paso', 2);
    }

    public function test_mapeo_requerido_sin_asignar_bloquea_avance(): void
    {
        [$proyecto] = $this->contextoCobranza();
        $cartera = $this->crearCarteraEn($proyecto, 'CART_T');

        $csv = "ced,nom,ape\n100,Ana,Diaz\n";
        $archivo = UploadedFile::fake()->createWithContent('libre.csv', $csv);

        $c = Livewire::test(Importar::class)
            ->set('targetValor', TargetImportacion::CASO_COBRANZA->value)
            ->set('carteraId', (int) $cartera->id)
            ->set('archivo', $archivo)
            ->call('subirArchivo')
            ->assertSet('paso', 2);

        $columnas = $c->get('columnas');
        $this->assertNotEmpty($columnas, 'Debe haber columnas inferidas');

        $nombresOriginales = array_column($columnas, 'nombre_original');
        $this->assertContains('ced', $nombresOriginales);
        $this->assertContains('nom', $nombresOriginales);
        $this->assertContains('ape', $nombresOriginales);
    }

    public function test_importar_caso_crea_persona_si_no_existe(): void
    {
        [$proyecto, $admin] = $this->contextoCobranzaAdmin();
        $cartera = $this->crearCarteraEn($proyecto, 'CART_T');
        $this->crearEstadoCasoEn($proyecto, 'ABIERTO');

        $csv = "Cartera,TipoIdentificacion,Identificacion,Nombres,Apellidos,NumeroPrestamo,Moneda,MO,SC,ST,FD,FV,Cu\n"
              ."CART_T,CED,1700000999,Carlos,Mendez,PR-AUTO-1,USD,1000,800,800,2025-10-01,2026-10-01,12\n";
        $archivo = UploadedFile::fake()->createWithContent('uni.csv', $csv);

        $c = Livewire::test(Importar::class)
            ->set('targetValor', TargetImportacion::CASO_COBRANZA->value)
            ->set('carteraId', (int) $cartera->id)
            ->set('archivo', $archivo)
            ->call('subirArchivo')
            ->assertHasNoErrors()
            ->assertSet('paso', 2);

        $c->call('marcarComoIdentificador', 'Identificacion')
            ->call('confirmarMapeo')
            ->assertHasNoErrors();

        $importacionId = $c->get('importacionId');
        $c->call('ejecutar');

        $this->assertDatabaseHas('importaciones', [
            'id' => $importacionId,
            'estado' => 'completada',
            'procesadas' => 1,
        ]);
        $this->assertDatabaseHas('personas', [
            'identificacion' => '1700000999',
            'proyecto_id' => $proyecto->id,
            'nombres' => 'Carlos',
            'apellidos' => 'Mendez',
            'tipo_persona' => 'fisica',
        ]);
        $this->assertDatabaseHas('casos_cobranza', ['numero_prestamo' => 'PR-AUTO-1']);
    }

    public function test_importar_caso_reusa_persona_si_ya_existe(): void
    {
        [$proyecto, $admin] = $this->contextoCobranzaAdmin();
        $cartera = $this->crearCarteraEn($proyecto, 'CART_T');
        $this->crearEstadoCasoEn($proyecto, 'ABIERTO');
        $this->crearPersonaEn($proyecto, '1700000888');

        $personasAntes = (int) DB::table('personas')->where('proyecto_id', $proyecto->id)->count();

        $csv = "Cartera,TipoIdentificacion,Identificacion,Nombres,Apellidos,NumeroPrestamo,Moneda,MO,SC,ST,FD,FV,Cu\n"
              ."CART_T,CED,1700000888,OtroNombre,OtroApe,PR-REUSE-1,USD,1,1,1,2025-10-01,2026-10-01,12\n";
        $archivo = UploadedFile::fake()->createWithContent('reuse.csv', $csv);

        $c = Livewire::test(Importar::class)
            ->set('targetValor', TargetImportacion::CASO_COBRANZA->value)
            ->set('carteraId', (int) $cartera->id)
            ->set('archivo', $archivo)
            ->call('subirArchivo')
            ->assertHasNoErrors()
            ->assertSet('paso', 2);

        $c->call('marcarComoIdentificador', 'Identificacion')
            ->call('confirmarMapeo')
            ->assertHasNoErrors();

        $c->call('ejecutar');

        $personasDespues = (int) DB::table('personas')->where('proyecto_id', $proyecto->id)->count();
        $this->assertSame($personasAntes, $personasDespues, 'No debe duplicar persona existente');
        $this->assertDatabaseHas('casos_cobranza', ['numero_prestamo' => 'PR-REUSE-1']);
    }

    public function test_importar_persona_juridica_se_infiere_de_razon_social(): void
    {
        [$proyecto, $admin] = $this->contextoCobranzaAdmin();
        $cartera = $this->crearCarteraEn($proyecto, 'CART_T');
        $this->crearEstadoCasoEn($proyecto, 'ABIERTO');

        $csv = "Cartera,TipoIdentificacion,Identificacion,RazonSocial,NumeroPrestamo,Moneda,MO,SC,ST,FD,FV,Cu\n"
              ."CART_T,RUC,1799000010,Empresa SA,PR-JUR-1,USD,1,1,1,2025-10-01,2026-10-01,12\n";
        $archivo = UploadedFile::fake()->createWithContent('jur.csv', $csv);

        $c = Livewire::test(Importar::class)
            ->set('targetValor', TargetImportacion::CASO_COBRANZA->value)
            ->set('carteraId', (int) $cartera->id)
            ->set('archivo', $archivo)
            ->call('subirArchivo')
            ->assertHasNoErrors()
            ->assertSet('paso', 2);

        $c->call('marcarComoIdentificador', 'Identificacion')
            ->call('confirmarMapeo')
            ->assertHasNoErrors();

        $c->call('ejecutar');

        $this->assertDatabaseHas('personas', [
            'identificacion' => '1799000010',
            'proyecto_id' => $proyecto->id,
            'tipo_persona' => 'juridica',
            'razon_social' => 'Empresa SA',
        ]);
    }

    public function test_dry_run_marca_invalida_si_persona_nueva_sin_nombres(): void
    {
        [$proyecto, $admin] = $this->contextoCobranzaAdmin();
        $cartera = $this->crearCarteraEn($proyecto, 'CART_T');
        $this->crearEstadoCasoEn($proyecto, 'ABIERTO');

        $csv = "Cartera,TipoIdentificacion,Identificacion,NumeroPrestamo,Moneda,MO,SC,ST,FD,FV,Cu\n"
              ."CART_T,CED,1700000777,PR-X,USD,1,1,1,2025-10-01,2026-10-01,12\n";
        $archivo = UploadedFile::fake()->createWithContent('sin_nom.csv', $csv);

        $c = Livewire::test(Importar::class)
            ->set('targetValor', TargetImportacion::CASO_COBRANZA->value)
            ->set('carteraId', (int) $cartera->id)
            ->set('archivo', $archivo)
            ->call('subirArchivo')
            ->assertHasNoErrors()
            ->assertSet('paso', 2);

        $c->call('marcarComoIdentificador', 'Identificacion')
            ->call('confirmarMapeo')
            ->assertHasNoErrors();

        $importacionId = $c->get('importacionId');
        $c->call('ejecutar');

        $this->assertDatabaseHas('importaciones', [
            'id' => $importacionId,
            'validas' => 0,
            'invalidas' => 1,
        ]);
    }

    public function test_aislamiento_entre_proyectos(): void
    {
        $mandante = $this->crearMandante();
        $proyectoA = $this->crearProyectoCobranza($mandante);
        $proyectoB = $this->crearProyectoCobranza($mandante);
        $supA = $this->crearSupervisor($proyectoA);

        DB::table('importaciones')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoB->id,
            'tipo_entidad' => 'caso_cobranza',
            'modo' => 'merge',
            'estado' => 'completada',
            'usuario_id' => $supA->id,
            'nombre_archivo' => 'b.csv',
            'total_filas' => 1,
        ]);
        DB::table('importaciones')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoA->id,
            'tipo_entidad' => 'caso_cobranza',
            'modo' => 'merge',
            'estado' => 'completada',
            'usuario_id' => $supA->id,
            'nombre_archivo' => 'a.csv',
            'total_filas' => 1,
        ]);

        $this->activarProyecto($proyectoA);
        $this->actingAs($supA);

        $c = Livewire::test(Importar::class);
        $historial = $c->viewData('historial');

        $codigosVistos = collect($historial)->pluck('nombre_archivo')->all();
        $this->assertContains('a.csv', $codigosVistos);
        $this->assertNotContains('b.csv', $codigosVistos);
    }

    public function test_csv_sin_filas_de_datos_se_rechaza(): void
    {
        [$proyecto] = $this->contextoCobranza();
        $cartera = $this->crearCarteraEn($proyecto, 'CART_T');

        $csv = "ced,nombre\n";
        $archivo = UploadedFile::fake()->createWithContent('vacio.csv', $csv);

        Livewire::test(Importar::class)
            ->set('targetValor', TargetImportacion::CASO_COBRANZA->value)
            ->set('carteraId', (int) $cartera->id)
            ->set('archivo', $archivo)
            ->call('subirArchivo')
            ->assertHasErrors('archivo');
    }

    public function test_descargar_plantilla_xlsx_caso_cobranza(): void
    {
        [$proyecto, $supervisor] = $this->contextoCobranza();

        $resp = $this->actingAs($supervisor)
            ->get(route('proyectos.importaciones.plantilla', [
                'proyecto_id' => $proyecto->id,
                'target' => TargetImportacion::CASO_COBRANZA->value,
            ]));

        $resp->assertStatus(200);
        $resp->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $contenido = $resp->streamedContent();
        $this->assertStringStartsWith('PK', $contenido);
    }

    public function test_descargar_plantilla_target_persona_ya_no_aplica_403(): void
    {
        [$proyecto, $supervisor] = $this->contextoCobranza();

        $this->actingAs($supervisor)
            ->get(route('proyectos.importaciones.plantilla', [
                'proyecto_id' => $proyecto->id,
                'target' => 'persona',
            ]))
            ->assertStatus(403);
    }

    public function test_descargar_plantilla_target_invalido_404(): void
    {
        [$proyecto, $supervisor] = $this->contextoCobranza();

        $this->actingAs($supervisor)
            ->get(route('proyectos.importaciones.plantilla', [
                'proyecto_id' => $proyecto->id,
                'target' => 'no_existe',
            ]))
            ->assertStatus(404);
    }

    public function test_subir_xlsx_funciona_igual_que_csv(): void
    {
        [$proyecto, $admin] = $this->contextoCobranzaAdmin();
        $cartera = $this->crearCarteraEn($proyecto, 'CART_T');
        $this->crearEstadoCasoEn($proyecto, 'ABIERTO');

        $xlsxPath = tempnam(sys_get_temp_dir(), 'imp_').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($xlsxPath);
        $writer->addRow(Row::fromValues(['Cartera', 'TipoIdentificacion', 'Identificacion', 'Nombres', 'Apellidos', 'NumeroPrestamo', 'Moneda', 'MO', 'SC', 'ST', 'FD', 'FV', 'Cu']));
        $writer->addRow(Row::fromValues(['CART_T', 'CED', '7700000111', 'Pedro', 'Ramos', 'PR-XLSX-1', 'USD', '1', '1', '1', '2025-10-01', '2026-10-01', '12']));
        $writer->close();

        $archivo = UploadedFile::fake()->createWithContent('caso.xlsx', (string) file_get_contents($xlsxPath));

        $c = Livewire::test(Importar::class)
            ->set('targetValor', TargetImportacion::CASO_COBRANZA->value)
            ->set('carteraId', (int) $cartera->id)
            ->set('archivo', $archivo)
            ->call('subirArchivo')
            ->assertHasNoErrors()
            ->assertSet('paso', 2);

        $c->call('marcarComoIdentificador', 'Identificacion')
            ->call('confirmarMapeo')
            ->assertHasNoErrors();

        $c->call('ejecutar');

        $this->assertDatabaseHas('personas', ['identificacion' => '7700000111', 'proyecto_id' => $proyecto->id]);
        $this->assertDatabaseHas('casos_cobranza', ['numero_prestamo' => 'PR-XLSX-1']);

        @unlink($xlsxPath);
    }

    public function test_procesar_es_sincrono_no_requiere_worker(): void
    {
        [$proyecto, $admin] = $this->contextoCobranzaAdmin();
        $cartera = $this->crearCarteraEn($proyecto, 'CART_T');
        $this->crearEstadoCasoEn($proyecto, 'ABIERTO');

        $csv = "Cartera,TipoIdentificacion,Identificacion,Nombres,NumeroPrestamo,Moneda,MO,SC,ST,FD,FV,Cu\n"
              ."CART_T,CED,5500009999,Sync,PR-SYNC,USD,1,1,1,2025-10-01,2026-10-01,12\n";
        $archivo = UploadedFile::fake()->createWithContent('sync.csv', $csv);

        $c = Livewire::test(Importar::class)
            ->set('targetValor', TargetImportacion::CASO_COBRANZA->value)
            ->set('carteraId', (int) $cartera->id)
            ->set('archivo', $archivo)
            ->call('subirArchivo')
            ->assertHasNoErrors()
            ->assertSet('paso', 2);

        $c->call('marcarComoIdentificador', 'Identificacion')
            ->call('confirmarMapeo')
            ->assertHasNoErrors();

        $importacionId = $c->get('importacionId');
        $c->call('ejecutar');

        $imp = DB::table('importaciones')->where('id', $importacionId)->first();
        $this->assertSame('completada', (string) $imp->estado);
        $this->assertSame(1, (int) $imp->procesadas);
    }

    /** @return array{0: stdClass, 1: User} */
    private function contextoCobranza(): array
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);
        $this->activarProyecto($proyecto);
        $this->actingAs($supervisor);

        return [$proyecto, $supervisor];
    }

    /** @return array{0: stdClass, 1: User} */
    private function contextoCobranzaAdmin(): array
    {
        $proyecto = $this->crearProyectoCobranza();
        $admin = $this->crearAdminGlobal();
        $this->activarProyecto($proyecto);
        $this->actingAs($admin);

        return [$proyecto, $admin];
    }
}
