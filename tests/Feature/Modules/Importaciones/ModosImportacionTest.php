<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use App\Models\User;
use App\Modules\Importaciones\Application\UseCases\ProcesarImportacionPersonas;
use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * F31: verifica los 3 modos sobre personas.
 * - merge: rellena solo nulos
 * - overwrite: pisa todo con valores no-null
 * - skip_duplicados: marca duplicada, no toca registro
 */
final class ModosImportacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

    }

    public function test_modo_merge_solo_rellena_columnas_vacias(): void
    {
        [$proyectoId, $supervisor] = $this->setupContexto();

        DB::table('personas')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'tipo_persona' => 'fisica',
            'tipo_identificacion_id' => $this->idTipoCed(),
            'identificacion' => '5500000001',
            'nombres' => 'Juan',
            'apellidos' => null,
            'fecha_nacimiento' => null,
        ]);

        $importacionId = $this->crearImportacionConFila($proyectoId, $supervisor->id, [
            'tipo_persona' => 'fisica',
            'tipo_identificacion_codigo' => 'CED',
            'identificacion' => '5500000001',
            'nombres' => 'Juan Carlos',
            'apellidos' => 'Pérez',
            'fecha_nacimiento' => '1990-01-15',
        ]);

        app(ProcesarImportacionPersonas::class)->ejecutar(
            $importacionId,
            commit: true,
            modo: ModoImportacion::MERGE,
        );

        $persona = DB::table('personas')->where('identificacion', '5500000001')->first();
        $this->assertSame('Juan', $persona->nombres, 'merge no debe pisar campos llenos');
        $this->assertSame('Pérez', $persona->apellidos, 'merge debe rellenar campos nulos');
        $this->assertNotNull($persona->fecha_nacimiento, 'merge debe rellenar fecha_nacimiento nula');
    }

    public function test_modo_overwrite_pisa_todos_los_campos(): void
    {
        [$proyectoId, $supervisor] = $this->setupContexto();

        DB::table('personas')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'tipo_persona' => 'fisica',
            'tipo_identificacion_id' => $this->idTipoCed(),
            'identificacion' => '5500000002',
            'nombres' => 'Juan',
            'apellidos' => 'Apellido viejo',
        ]);

        $importacionId = $this->crearImportacionConFila($proyectoId, $supervisor->id, [
            'tipo_persona' => 'fisica',
            'tipo_identificacion_codigo' => 'CED',
            'identificacion' => '5500000002',
            'nombres' => 'Juan Carlos',
            'apellidos' => 'Apellido nuevo',
        ]);

        app(ProcesarImportacionPersonas::class)->ejecutar(
            $importacionId,
            commit: true,
            modo: ModoImportacion::OVERWRITE,
        );

        $persona = DB::table('personas')->where('identificacion', '5500000002')->first();
        $this->assertSame('Juan Carlos', $persona->nombres);
        $this->assertSame('Apellido nuevo', $persona->apellidos);
    }

    public function test_modo_skip_duplicados_no_toca_registro_y_marca_fila(): void
    {
        [$proyectoId, $supervisor] = $this->setupContexto();

        DB::table('personas')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'tipo_persona' => 'fisica',
            'tipo_identificacion_id' => $this->idTipoCed(),
            'identificacion' => '5500000003',
            'nombres' => 'Original',
        ]);

        $importacionId = $this->crearImportacionConFila($proyectoId, $supervisor->id, [
            'tipo_persona' => 'fisica',
            'tipo_identificacion_codigo' => 'CED',
            'identificacion' => '5500000003',
            'nombres' => 'CSV Nuevo',
        ]);

        app(ProcesarImportacionPersonas::class)->ejecutar(
            $importacionId,
            commit: true,
            modo: ModoImportacion::SKIP_DUPLICADOS,
        );

        $persona = DB::table('personas')->where('identificacion', '5500000003')->first();
        $this->assertSame('Original', $persona->nombres, 'skip_duplicados no debe modificar el registro');

        $this->assertDatabaseHas('importacion_filas', [
            'importacion_id' => $importacionId,
            'estado' => 'duplicada',
        ]);
    }

    public function test_overwrite_no_pisa_con_null_si_csv_trae_vacio(): void
    {
        [$proyectoId, $supervisor] = $this->setupContexto();

        DB::table('personas')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'tipo_persona' => 'fisica',
            'tipo_identificacion_id' => $this->idTipoCed(),
            'identificacion' => '5500000004',
            'nombres' => 'Juan',
            'apellidos' => 'Pérez',
        ]);

        $importacionId = $this->crearImportacionConFila($proyectoId, $supervisor->id, [
            'tipo_persona' => 'fisica',
            'tipo_identificacion_codigo' => 'CED',
            'identificacion' => '5500000004',
            'nombres' => 'Juan Modificado',
            'apellidos' => '',
        ]);

        app(ProcesarImportacionPersonas::class)->ejecutar(
            $importacionId,
            commit: true,
            modo: ModoImportacion::OVERWRITE,
        );

        $persona = DB::table('personas')->where('identificacion', '5500000004')->first();
        $this->assertSame('Juan Modificado', $persona->nombres);
        $this->assertSame('Pérez', $persona->apellidos, 'CSV vacío no debe sobreescribir a null');
    }

    private function setupContexto(): array
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));

        $rolId = (int) DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id');
        $u = User::query()->create([
            'name' => 'Sup',
            'email' => 'sup.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $u->id, 'proyecto_id' => $proyectoId,
            'rol_id' => $rolId, 'activo' => true,
        ]);
        $this->actingAs($u);

        return [$proyectoId, $u];
    }

    private function idTipoCed(): int
    {
        return (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
    }

    /** @param array<string,string> $payload */
    private function crearImportacionConFila(int $proyectoId, int $usuarioId, array $payload): int
    {
        $importacionId = (int) DB::table('importaciones')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'tipo_entidad' => 'persona',
            'modo' => 'merge',
            'estado' => EstadoImportacion::PREPARADA->value,
            'usuario_id' => $usuarioId,
            'nombre_archivo' => 'test.csv',
            'total_filas' => 1,
        ]);

        DB::table('importacion_filas')->insert([
            'importacion_id' => $importacionId,
            'proyecto_id' => $proyectoId,
            'numero_fila' => 1,
            'estado' => 'pendiente',
            'payload' => json_encode($payload),
        ]);

        return $importacionId;
    }
}
