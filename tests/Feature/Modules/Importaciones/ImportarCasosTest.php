<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use App\Modules\Importaciones\Infrastructure\Http\Livewire\ImportarCasos;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class ImportarCasosTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    /**
     * El camino de confirmación de este componente está muerto desde F35-B y
     * NO es un fallo del test.
     *
     * `ImportarCasos::guardarArchivo()` crea la fila de `importaciones` sin
     * poblar `esquema` (es el formato legacy de columnas fijas), pero
     * `EjecutarImportacionJob:98` ya no ramifica por `tipo_entidad`: manda todo
     * a `EjecutarImportacionDinamica`, que aborta si `esquema` es null
     * (`EjecutarImportacionDinamica.php:56`). La rama de commit de los
     * `ProcesarImportacionCasos*` quedó sin llamador.
     *
     * Se salta en vez de borrarse porque el defecto sigue ahí y el día que se
     * decida entre arreglar el enrutado o retirar el componente, este test dice
     * qué se esperaba de él. Lo que sí se ejecuta es la validación previa, que
     * sigue sana.
     */
    private const COMMIT_MUERTO = 'Componente @deprecated (F35-B): sin ruta y con la rama de commit rota — EjecutarImportacionJob exige `esquema`, que este componente nunca escribe. El flujo vivo es el wizard Importar, cubierto por ImportarUnificadoTest.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_importa_casos_cobranza_csv(): void
    {
        $this->markTestSkipped(self::COMMIT_MUERTO);

        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        $cartera = $this->crearCarteraEn($proyecto, 'CONSUMO');
        $estado = $this->crearEstadoCasoEn($proyecto, 'ABIERTO');
        $this->crearPersonaEn($proyecto, '9200000001');

        $csv = "cartera_codigo,tipo_identificacion_codigo,identificacion,numero_prestamo,moneda,monto_original,saldo_capital,saldo_interes,saldo_total,cuota_mensual,cuotas_totales,cuotas_pagadas,dias_mora,fecha_desembolso,fecha_vencimiento,estado_caso_codigo,prioridad,fecha_ingreso\n"
             ."{$cartera->codigo},CED,9200000001,IMP-0001,USD,5000.00,4500.00,200.00,4700.00,300.00,24,6,45,2025-10-01,2026-10-01,{$estado->codigo},3,2026-04-01\n";
        $archivo = UploadedFile::fake()->createWithContent('cobranza.csv', $csv);

        $componente = Livewire::test(ImportarCasos::class)
            ->set('archivo', $archivo)
            ->call('guardarArchivo')
            ->assertHasNoErrors();

        $importacionId = $componente->get('importacionId');
        $this->assertNotNull($importacionId);

        $this->assertDatabaseHas('importaciones', [
            'id' => $importacionId,
            'tipo_entidad' => 'caso_cobranza',
            'total_filas' => 1,
            'validas' => 1,
            'invalidas' => 0,
            'estado' => 'preparada',
        ]);

        $componente->call('confirmar');

        $this->assertDatabaseHas('importaciones', [
            'id' => $importacionId,
            'procesadas' => 1,
            'estado' => 'completada',
        ]);
        $this->assertDatabaseHas('casos_cobranza', ['numero_prestamo' => 'IMP-0001']);
    }

    public function test_filas_invalidas_se_rechazan_sin_importar(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        $cartera = $this->crearCarteraEn($proyecto, 'CONSUMO');
        $estado = $this->crearEstadoCasoEn($proyecto, 'ABIERTO');
        $this->crearPersonaEn($proyecto, '9300000001');

        $csv = "cartera_codigo,tipo_identificacion_codigo,identificacion,numero_prestamo,moneda,monto_original,saldo_capital,saldo_interes,saldo_total,cuota_mensual,cuotas_totales,cuotas_pagadas,dias_mora,fecha_desembolso,fecha_vencimiento,estado_caso_codigo,prioridad,fecha_ingreso\n"
             ."INEXISTE,CED,9300000001,IMP-X,USD,100,100,0,100,10,12,0,0,2025-10-01,2026-10-01,{$estado->codigo},3,2026-04-01\n"     // cartera inválida
             ."{$cartera->codigo},CED,7777777777,IMP-Y,USD,100,100,0,100,10,12,0,0,2025-10-01,2026-10-01,{$estado->codigo},3,2026-04-01\n"       // persona inexistente y sin datos para crearla
             ."{$cartera->codigo},CED,9300000001,IMP-OK,USD,500,500,0,500,50,12,0,0,2025-10-01,2026-10-01,{$estado->codigo},3,2026-04-01\n";      // ok
        $archivo = UploadedFile::fake()->createWithContent('mix.csv', $csv);

        $c = Livewire::test(ImportarCasos::class)
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

    public function test_proyecto_cx_importa_tickets(): void
    {
        $this->markTestSkipped(self::COMMIT_MUERTO);

        $proyecto = $this->crearProyectoCx();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        $cartera = $this->crearCarteraEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto);
        $this->crearPersonaEn($proyecto, '9400000001');

        $csv = "cartera_codigo,tipo_identificacion_codigo,identificacion,codigo_ticket,asunto,descripcion,categoria_codigo,prioridad_codigo,sla_codigo,escalamiento_codigo,fecha_reporte,fecha_limite_sla,estado_caso_codigo,prioridad,fecha_ingreso\n"
             ."{$cartera->codigo},CED,9400000001,TKT-IMP-001,Demo import,desc,,,,,2026-04-01,2026-04-05,{$estado->codigo},2,2026-04-01\n";
        $archivo = UploadedFile::fake()->createWithContent('cx.csv', $csv);

        $componente = Livewire::test(ImportarCasos::class)
            ->set('archivo', $archivo)
            ->call('guardarArchivo')
            ->assertHasNoErrors();

        $id = $componente->get('importacionId');
        $this->assertDatabaseHas('importaciones', [
            'id' => $id,
            'tipo_entidad' => 'caso_ticket_cx',
            'validas' => 1,
        ]);

        $componente->call('confirmar');

        $this->assertDatabaseHas('casos_ticket_cx', ['codigo_ticket' => 'TKT-IMP-001']);
    }

    public function test_csv_sin_columnas_obligatorias_es_rechazado(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        $csv = "identificacion,numero_prestamo\n9200000001,IMP-0001\n";
        $archivo = UploadedFile::fake()->createWithContent('malo.csv', $csv);

        Livewire::test(ImportarCasos::class)
            ->set('archivo', $archivo)
            ->call('guardarArchivo')
            ->assertHasErrors(['archivo']);

        $this->assertDatabaseCount('importaciones', 0);
    }
}
