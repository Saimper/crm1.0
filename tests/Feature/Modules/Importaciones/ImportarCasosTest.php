<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use App\Models\User;
use App\Modules\Importaciones\Infrastructure\Http\Livewire\ImportarCasos;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class ImportarCasosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_importa_casos_cobranza_csv(): void
    {
        $proyectoId = $this->proyectoCobranzaId();
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));
        $supervisor = $this->crearUsuarioConRol($proyectoId, 'SUPERVISOR');
        $this->actingAs($supervisor);

        $this->crearPersonaCED($proyectoId, '9200000001');

        $csv = "cartera_codigo,tipo_identificacion_codigo,identificacion,numero_prestamo,moneda,monto_original,saldo_capital,saldo_interes,saldo_total,cuota_mensual,cuotas_totales,cuotas_pagadas,dias_mora,fecha_desembolso,fecha_vencimiento,estado_caso_codigo,prioridad,fecha_ingreso\n"
             ."CONSUMO,CED,9200000001,IMP-0001,USD,5000.00,4500.00,200.00,4700.00,300.00,24,6,45,2025-10-01,2026-10-01,ABIERTO,3,2026-04-01\n";
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
            'filas_ok' => 1,
            'filas_error' => 0,
            'estado' => 'validada',
        ]);

        $componente->call('confirmar');

        $this->assertDatabaseHas('importaciones', [
            'id' => $importacionId,
            'filas_importadas' => 1,
            'estado' => 'completada',
        ]);
        $this->assertDatabaseHas('casos_cobranza', ['numero_prestamo' => 'IMP-0001']);
    }

    public function test_filas_invalidas_se_rechazan_sin_importar(): void
    {
        $proyectoId = $this->proyectoCobranzaId();
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));
        $this->actingAs($this->crearUsuarioConRol($proyectoId, 'SUPERVISOR'));

        $this->crearPersonaCED($proyectoId, '9300000001');

        $csv = "cartera_codigo,tipo_identificacion_codigo,identificacion,numero_prestamo,moneda,monto_original,saldo_capital,saldo_interes,saldo_total,cuota_mensual,cuotas_totales,cuotas_pagadas,dias_mora,fecha_desembolso,fecha_vencimiento,estado_caso_codigo,prioridad,fecha_ingreso\n"
             ."INEXISTE,CED,9300000001,IMP-X,USD,100,100,0,100,10,12,0,0,2025-10-01,2026-10-01,ABIERTO,3,2026-04-01\n"     // cartera inválida
             ."CONSUMO,CED,7777777777,IMP-Y,USD,100,100,0,100,10,12,0,0,2025-10-01,2026-10-01,ABIERTO,3,2026-04-01\n"       // persona inexistente
             ."CONSUMO,CED,9300000001,IMP-OK,USD,500,500,0,500,50,12,0,0,2025-10-01,2026-10-01,ABIERTO,3,2026-04-01\n";      // ok
        $archivo = UploadedFile::fake()->createWithContent('mix.csv', $csv);

        $c = Livewire::test(ImportarCasos::class)
            ->set('archivo', $archivo)
            ->call('guardarArchivo')
            ->assertHasNoErrors();

        $id = $c->get('importacionId');
        $this->assertDatabaseHas('importaciones', [
            'id' => $id,
            'total_filas' => 3,
            'filas_ok' => 1,
            'filas_error' => 2,
            'estado' => 'validada',
        ]);
    }

    public function test_proyecto_cx_importa_tickets(): void
    {
        $proyectoId = $this->proyectoCxId();
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));
        $this->actingAs($this->crearUsuarioConRol($proyectoId, 'SUPERVISOR'));

        $this->crearPersonaCED($proyectoId, '9400000001');

        $carteraCodigo = (string) DB::table('carteras')->where('proyecto_id', $proyectoId)->value('codigo');
        $estadoCodigo = (string) DB::table('estados_caso')->where('proyecto_id', $proyectoId)->value('codigo');

        $csv = "cartera_codigo,tipo_identificacion_codigo,identificacion,codigo_ticket,asunto,descripcion,categoria_codigo,prioridad_codigo,sla_codigo,escalamiento_codigo,fecha_reporte,fecha_limite_sla,estado_caso_codigo,prioridad,fecha_ingreso\n"
             ."{$carteraCodigo},CED,9400000001,TKT-IMP-001,Demo import,desc,,,,,2026-04-01,2026-04-05,{$estadoCodigo},2,2026-04-01\n";
        $archivo = UploadedFile::fake()->createWithContent('cx.csv', $csv);

        $componente = Livewire::test(ImportarCasos::class)
            ->set('archivo', $archivo)
            ->call('guardarArchivo')
            ->assertHasNoErrors();

        $id = $componente->get('importacionId');
        $this->assertDatabaseHas('importaciones', [
            'id' => $id,
            'tipo_entidad' => 'caso_ticket_cx',
            'filas_ok' => 1,
        ]);

        $componente->call('confirmar');

        $this->assertDatabaseHas('casos_ticket_cx', ['codigo_ticket' => 'TKT-IMP-001']);
    }

    public function test_csv_sin_columnas_obligatorias_es_rechazado(): void
    {
        $proyectoId = $this->proyectoCobranzaId();
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));
        $this->actingAs($this->crearUsuarioConRol($proyectoId, 'SUPERVISOR'));

        $csv = "identificacion,numero_prestamo\n9200000001,IMP-0001\n";
        $archivo = UploadedFile::fake()->createWithContent('malo.csv', $csv);

        Livewire::test(ImportarCasos::class)
            ->set('archivo', $archivo)
            ->call('guardarArchivo')
            ->assertHasErrors(['archivo']);

        $this->assertDatabaseCount('importaciones', 0);
    }

    private function proyectoCobranzaId(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
    }

    private function proyectoCxId(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
    }

    private function crearPersonaCED(int $proyectoId, string $identificacion): void
    {
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        DB::table('personas')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'tipo_persona' => 'fisica',
            'tipo_identificacion_id' => $tipoCed,
            'identificacion' => $identificacion,
            'nombres' => 'Import',
            'apellidos' => 'Test',
        ]);
    }

    private function crearUsuarioConRol(int $proyectoId, string $codigoRol): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name' => ucfirst(strtolower($codigoRol)),
            'email' => strtolower($codigoRol).'.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);
        $rolId = (int) DB::table('roles')->where('codigo', $codigoRol)->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $u->id, 'proyecto_id' => $proyectoId,
            'rol_id' => $rolId, 'activo' => true,
        ]);

        return $u;
    }
}
