<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class PurgarImportacionesObsoletasTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_elimina_solo_las_nunca_lanzadas_con_antiguedad_suficiente(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $usuario = $this->crearAdminGlobal();
        $proyectoId = (int) $proyecto->id;
        $usuarioId = (int) $usuario->id;

        $viejaPreparada = $this->importacion($proyectoId, $usuarioId, 'preparada', 10);
        $viejaPendiente = $this->importacion($proyectoId, $usuarioId, 'pendiente', 10);
        $recientePreparada = $this->importacion($proyectoId, $usuarioId, 'preparada', 1);
        $viejaCompletada = $this->importacion($proyectoId, $usuarioId, 'completada', 10);
        $this->filas($viejaPreparada, $proyectoId, 2);
        $this->filas($recientePreparada, $proyectoId, 1);

        $this->artisan('importaciones:purgar-obsoletas', ['--dias' => 7])
            ->expectsOutputToContain('eliminadas: 2')
            ->assertSuccessful();

        $this->assertDatabaseMissing('importaciones', ['id' => $viejaPreparada]);
        $this->assertDatabaseMissing('importaciones', ['id' => $viejaPendiente]);
        $this->assertDatabaseHas('importaciones', ['id' => $recientePreparada]);
        $this->assertDatabaseHas('importaciones', ['id' => $viejaCompletada]);
        $this->assertDatabaseCount('importacion_filas', 1);
    }

    private function importacion(int $proyectoId, int $usuarioId, string $estado, int $diasAtras): int
    {
        return (int) DB::table('importaciones')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'tipo_entidad' => 'caso_cobranza',
            'modo' => 'upsert',
            'estado' => $estado,
            'usuario_id' => $usuarioId,
            'nombre_archivo' => "archivo-{$estado}.xlsx",
            'total_filas' => 1,
            'creada_en' => CarbonImmutable::now()->subDays($diasAtras),
            'actualizada_en' => CarbonImmutable::now()->subDays($diasAtras),
        ]);
    }

    private function filas(int $importacionId, int $proyectoId, int $cantidad): void
    {
        for ($i = 1; $i <= $cantidad; $i++) {
            DB::table('importacion_filas')->insert([
                'importacion_id' => $importacionId,
                'proyecto_id' => $proyectoId,
                'numero_fila' => $i,
                'estado' => 'pendiente',
                'payload' => json_encode(['CEDULA' => "8-000-{$i}"], JSON_THROW_ON_ERROR),
            ]);
        }
    }
}
