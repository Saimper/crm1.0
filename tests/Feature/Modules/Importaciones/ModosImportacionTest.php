<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use App\Models\User;
use App\Modules\Importaciones\Application\UseCases\ProcesarImportacionPersonas;
use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * F31: verifica los 3 modos sobre personas.
 * - merge: rellena solo nulos
 * - overwrite: pisa todo con valores no-null
 * - skip_duplicados: marca duplicada, no toca registro
 */
final class ModosImportacionTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
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
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
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
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
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
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
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
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
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

    /** @return array{0: int, 1: User} */
    private function setupContexto(): array
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);

        $supervisor = $this->crearSupervisor($proyecto);
        $this->actingAs($supervisor);

        return [(int) $proyecto->id, $supervisor];
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
