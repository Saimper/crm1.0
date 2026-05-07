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
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * F35-B — wizard unificado con mapeo libre de columnas CSV.
 *
 * Sustituye a ImportarPersonasTest y ImportarCasosTest (skipped por migración a
 * EscenarioOperativo + cambio de UX). Cubre persona y caso_cobranza como targets
 * representativos; los demás CTI (CX, venta, servicio) reusan el mismo Livewire
 * y los procesadores async F31 sin cambios.
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

    public function test_pantalla_muestra_targets_disponibles_segun_tipo_proyecto_cobranza(): void
    {
        [$proyecto, $supervisor] = $this->contexto('cobranza');

        $componente = Livewire::test(Importar::class);
        $disponibles = $componente->viewData('targetsDisponibles');

        $this->assertSame(
            [TargetImportacion::PERSONA, TargetImportacion::CASO_COBRANZA],
            $disponibles,
        );
    }

    public function test_subir_csv_con_columnas_arbitrarias_no_falla(): void
    {
        [$proyecto] = $this->contexto('cobranza');

        $csv = "ced,nom,ape\n100,Ana,Diaz\n200,Luis,Paz\n";
        $archivo = UploadedFile::fake()->createWithContent('libre.csv', $csv);

        Livewire::test(Importar::class)
            ->set('targetValor', 'persona')
            ->set('archivo', $archivo)
            ->call('subirArchivo')
            ->assertHasNoErrors()
            ->assertSet('paso', 2)
            ->assertSet('cabecerasCsv', ['ced', 'nom', 'ape']);
    }

    public function test_mapeo_requerido_sin_asignar_bloquea_avance(): void
    {
        [$proyecto] = $this->contexto('cobranza');

        $csv = "ced,nom,ape\n100,Ana,Diaz\n";
        $archivo = UploadedFile::fake()->createWithContent('libre.csv', $csv);

        $c = Livewire::test(Importar::class)
            ->set('targetValor', 'persona')
            ->set('archivo', $archivo)
            ->call('subirArchivo')
            // mapeo vacío de tipo_identificacion_codigo (requerido)
            ->set('mapeo.tipo_identificacion_codigo', '')
            ->set('mapeo.identificacion', 'ced')
            ->set('mapeo.nombres', 'nom')
            ->call('confirmarMapeo')
            ->assertHasErrors('mapeo.tipo_identificacion_codigo');

        $this->assertSame(2, $c->get('paso'), 'No debe avanzar de paso si falta mapeo requerido');
        $this->assertDatabaseCount('importaciones', 0);
    }

    public function test_auto_mapeo_matchea_columnas_con_nombre_canonico(): void
    {
        [$proyecto] = $this->contexto('cobranza');

        $csv = "tipo identificacion codigo,identificacion,nombres\nCED,100,Ana\n";
        $archivo = UploadedFile::fake()->createWithContent('canon.csv', $csv);

        $c = Livewire::test(Importar::class)
            ->set('targetValor', 'persona')
            ->set('archivo', $archivo)
            ->call('subirArchivo');

        $this->assertSame('tipo identificacion codigo', $c->get('mapeo.tipo_identificacion_codigo'));
        $this->assertSame('identificacion', $c->get('mapeo.identificacion'));
        $this->assertSame('nombres', $c->get('mapeo.nombres'));
    }

    public function test_payload_canonico_se_construye_desde_mapeo(): void
    {
        [$proyecto] = $this->contexto('cobranza');

        $csv = "ced,nom,ape\n100,Ana,Diaz\n";
        $archivo = UploadedFile::fake()->createWithContent('libre.csv', $csv);

        Livewire::test(Importar::class)
            ->set('targetValor', 'persona')
            ->set('archivo', $archivo)
            ->call('subirArchivo')
            ->set('mapeo.tipo_identificacion_codigo', '')
            ->set('mapeo.identificacion', 'ced')
            ->set('mapeo.nombres', 'nom')
            ->set('mapeo.apellidos', 'ape');
        // Forzar tipo_identificacion_codigo via columna ficticia: no existe → debe quedar inválida.
        // Para verificar payload canónico, usamos otra estrategia: mapear identificación y verificar payload.

        // Re-test con todos los requeridos mapeados:
        $csv2 = "doc,ced,nom,ape\nCED,100,Ana,Diaz\n";
        $archivo2 = UploadedFile::fake()->createWithContent('libre2.csv', $csv2);

        Livewire::test(Importar::class)
            ->set('targetValor', 'persona')
            ->set('archivo', $archivo2)
            ->call('subirArchivo')
            ->set('mapeo.tipo_identificacion_codigo', 'doc')
            ->set('mapeo.identificacion', 'ced')
            ->set('mapeo.nombres', 'nom')
            ->set('mapeo.apellidos', 'ape')
            ->call('confirmarMapeo')
            ->assertHasNoErrors();

        $fila = DB::table('importacion_filas')->where('numero_fila', 1)->orderByDesc('id')->first();
        $this->assertNotNull($fila);
        $payload = json_decode((string) $fila->payload, true);

        $this->assertSame('CED', $payload['tipo_identificacion_codigo']);
        $this->assertSame('100', $payload['identificacion']);
        $this->assertSame('Ana', $payload['nombres']);
        $this->assertSame('Diaz', $payload['apellidos']);
    }

    public function test_persona_tipo_persona_se_infiere_de_razon_social(): void
    {
        [$proyecto] = $this->contexto('cobranza');

        $csv = "doc,id,raz\nRUC,1799000001,Empresa Demo SA\n";
        $archivo = UploadedFile::fake()->createWithContent('jur.csv', $csv);

        Livewire::test(Importar::class)
            ->set('targetValor', 'persona')
            ->set('archivo', $archivo)
            ->call('subirArchivo')
            ->set('mapeo.tipo_identificacion_codigo', 'doc')
            ->set('mapeo.identificacion', 'id')
            ->set('mapeo.razon_social', 'raz')
            ->set('mapeo.nombres', '')
            ->set('mapeo.apellidos', '')
            ->call('confirmarMapeo')
            ->assertHasNoErrors();

        $fila = DB::table('importacion_filas')->where('numero_fila', 1)->orderByDesc('id')->first();
        $payload = json_decode((string) $fila->payload, true);
        $this->assertSame('juridica', $payload['tipo_persona']);
    }

    public function test_persona_tipo_persona_se_infiere_de_nombres(): void
    {
        [$proyecto] = $this->contexto('cobranza');

        $csv = "doc,id,nom\nCED,2200000001,Rosa\n";
        $archivo = UploadedFile::fake()->createWithContent('fis.csv', $csv);

        Livewire::test(Importar::class)
            ->set('targetValor', 'persona')
            ->set('archivo', $archivo)
            ->call('subirArchivo')
            ->set('mapeo.tipo_identificacion_codigo', 'doc')
            ->set('mapeo.identificacion', 'id')
            ->set('mapeo.nombres', 'nom')
            ->call('confirmarMapeo')
            ->assertHasNoErrors();

        $fila = DB::table('importacion_filas')->where('numero_fila', 1)->orderByDesc('id')->first();
        $payload = json_decode((string) $fila->payload, true);
        $this->assertSame('fisica', $payload['tipo_persona']);
    }

    public function test_caso_cobranza_se_importa_con_csv_de_columnas_renombradas(): void
    {
        [$proyecto] = $this->contexto('cobranza');
        $cartera = $this->crearCarteraEn($proyecto, 'CART_TEST');
        $estado = $this->crearEstadoCasoEn($proyecto, 'ABIERTO');
        $this->crearPersonaEn($proyecto, '5500000001');

        // CSV cliente con columnas en español/libres
        $csv = "Cartera,TipoDoc,Documento,Prestamo,Mon,MontoOrig,SaldoCap,SaldoTot,Desemb,Vence,Cuotas\n"
              ."CART_TEST,CED,5500000001,IMP-X1,USD,5000.00,4500.00,4700.00,2025-10-01,2026-10-01,24\n";
        $archivo = UploadedFile::fake()->createWithContent('cob.csv', $csv);

        $c = Livewire::test(Importar::class)
            ->set('targetValor', TargetImportacion::CASO_COBRANZA->value)
            ->set('archivo', $archivo)
            ->call('subirArchivo')
            ->set('mapeo.cartera_codigo', 'Cartera')
            ->set('mapeo.tipo_identificacion_codigo', 'TipoDoc')
            ->set('mapeo.identificacion', 'Documento')
            ->set('mapeo.numero_prestamo', 'Prestamo')
            ->set('mapeo.moneda', 'Mon')
            ->set('mapeo.monto_original', 'MontoOrig')
            ->set('mapeo.saldo_capital', 'SaldoCap')
            ->set('mapeo.saldo_total', 'SaldoTot')
            ->set('mapeo.fecha_desembolso', 'Desemb')
            ->set('mapeo.fecha_vencimiento', 'Vence')
            ->set('mapeo.cuotas_totales', 'Cuotas')
            // estado_caso_codigo, fecha_ingreso opcionales: defaults
            ->call('confirmarMapeo')
            ->assertHasNoErrors();

        $importacionId = $c->get('importacionId');
        $this->assertNotNull($importacionId);

        $this->assertDatabaseHas('importaciones', [
            'id' => $importacionId,
            'tipo_entidad' => 'caso_cobranza',
            'total_filas' => 1,
            'validas' => 1,
            'invalidas' => 0,
            'estado' => 'preparada',
        ]);

        $c->call('procesar');

        $this->assertDatabaseHas('importaciones', [
            'id' => $importacionId,
            'estado' => 'completada',
            'procesadas' => 1,
        ]);
        $this->assertDatabaseHas('casos_cobranza', ['numero_prestamo' => 'IMP-X1']);
    }

    public function test_aislamiento_entre_proyectos(): void
    {
        $mandante = $this->crearMandante();
        $proyectoA = $this->crearProyectoCobranza($mandante);
        $proyectoB = $this->crearProyectoCobranza($mandante);

        $supA = $this->crearSupervisor($proyectoA);

        // Crear importacion en proyecto B (otro)
        DB::table('importaciones')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoB->id,
            'tipo_entidad' => 'persona',
            'modo' => 'merge',
            'estado' => 'completada',
            'usuario_id' => $supA->id,
            'nombre_archivo' => 'b.csv',
            'total_filas' => 1,
        ]);
        DB::table('importaciones')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoA->id,
            'tipo_entidad' => 'persona',
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

    public function test_dry_run_marca_filas_invalidas_persona_no_existente(): void
    {
        [$proyecto] = $this->contexto('cobranza');
        $cartera = $this->crearCarteraEn($proyecto, 'CART_T');
        $estado = $this->crearEstadoCasoEn($proyecto, 'ABIERTO');
        // NO se crea persona — la fila debe quedar invalida en dry-run

        $csv = "cart,doc,id,prest,mon,mo,sc,st,fd,fv\n"
              ."CART_T,CED,9999999999,IMP-NA,USD,1,1,1,2025-10-01,2026-10-01\n";
        $archivo = UploadedFile::fake()->createWithContent('na.csv', $csv);

        $c = Livewire::test(Importar::class)
            ->set('targetValor', TargetImportacion::CASO_COBRANZA->value)
            ->set('archivo', $archivo)
            ->call('subirArchivo')
            ->set('mapeo.cartera_codigo', 'cart')
            ->set('mapeo.tipo_identificacion_codigo', 'doc')
            ->set('mapeo.identificacion', 'id')
            ->set('mapeo.numero_prestamo', 'prest')
            ->set('mapeo.moneda', 'mon')
            ->set('mapeo.monto_original', 'mo')
            ->set('mapeo.saldo_capital', 'sc')
            ->set('mapeo.saldo_total', 'st')
            ->set('mapeo.fecha_desembolso', 'fd')
            ->set('mapeo.fecha_vencimiento', 'fv')
            ->call('confirmarMapeo')
            ->assertHasNoErrors();

        $id = $c->get('importacionId');
        $this->assertDatabaseHas('importaciones', [
            'id' => $id,
            'validas' => 0,
            'invalidas' => 1,
        ]);
    }

    public function test_csv_sin_filas_de_datos_se_rechaza_con_error_claro(): void
    {
        [$proyecto] = $this->contexto('cobranza');

        $csv = "ced,nombre\n"; // solo headers
        $archivo = UploadedFile::fake()->createWithContent('vacio.csv', $csv);

        Livewire::test(Importar::class)
            ->set('targetValor', 'persona')
            ->set('archivo', $archivo)
            ->call('subirArchivo')
            ->assertHasErrors('archivo');
    }

    public function test_persona_csv_renombrado_completa_inserta_persona(): void
    {
        [$proyecto] = $this->contexto('cobranza');

        // CSV con columnas inventadas — el cliente no necesita renombrar
        $csv = "tipo doc,Numero Documento,Primer Nombre,Apellido Paterno\n"
              ."CED,8800000777,Maria,Lopez\n";
        $archivo = UploadedFile::fake()->createWithContent('libre.csv', $csv);

        $c = Livewire::test(Importar::class)
            ->set('targetValor', 'persona')
            ->set('archivo', $archivo)
            ->call('subirArchivo')
            ->set('mapeo.tipo_identificacion_codigo', 'tipo doc')
            ->set('mapeo.identificacion', 'Numero Documento')
            ->set('mapeo.nombres', 'Primer Nombre')
            ->set('mapeo.apellidos', 'Apellido Paterno')
            ->call('confirmarMapeo')
            ->assertHasNoErrors();

        $c->call('procesar');

        $this->assertDatabaseHas('personas', [
            'identificacion' => '8800000777',
            'proyecto_id' => $proyecto->id,
            'nombres' => 'Maria',
            'apellidos' => 'Lopez',
        ]);
    }

    /** @return array{0: \stdClass, 1: User} */
    private function contexto(string $tipoOperacion): array
    {
        $proyecto = $this->crearProyecto($tipoOperacion);
        $supervisor = $this->crearSupervisor($proyecto);
        $this->activarProyecto($proyecto);
        $this->actingAs($supervisor);

        return [$proyecto, $supervisor];
    }
}
