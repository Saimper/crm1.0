<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * El archivo del cliente caduca.
 *
 * `importacion_filas.payload` guarda la fila cruda que subió el supervisor y
 * hasta ahora no la borraba nadie: meses de cédulas, teléfonos y saldos vivos
 * en una tabla que sólo crece. La retención por defecto son 30 días desde que
 * la importación terminó, que es lo que dura su único uso posterior —corregir
 * las filas rechazadas y volver a subirlas—.
 */
final class PurgarPayloadsTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_depura_lo_viejo_y_terminado_y_no_toca_nada_mas(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $usuario = $this->crearSupervisor($proyecto);

        $vieja = $this->importacionCon($proyecto, $usuario->id, 'completada', Carbon::now()->subDays(45));
        $reciente = $this->importacionCon($proyecto, $usuario->id, 'completada', Carbon::now()->subDays(3));
        $enCurso = $this->importacionCon($proyecto, $usuario->id, 'procesando', null);

        $this->artisan('importaciones:purgar-payloads')->assertSuccessful();

        $this->assertSame('{}', (string) DB::table('importacion_filas')->where('importacion_id', $vieja)->value('payload'));
        $this->assertNull(DB::table('importacion_filas')->where('importacion_id', $vieja)->value('mensaje_error'));
        $this->assertNotNull(DB::table('importaciones')->where('id', $vieja)->value('payload_purgado_en'));

        $this->assertStringContainsString('8-990-429', (string) DB::table('importacion_filas')->where('importacion_id', $reciente)->value('payload'));
        $this->assertNull(DB::table('importaciones')->where('id', $reciente)->value('payload_purgado_en'));

        $this->assertStringContainsString('8-990-429', (string) DB::table('importacion_filas')->where('importacion_id', $enCurso)->value('payload'));
        $this->assertNull(
            DB::table('importaciones')->where('id', $enCurso)->value('payload_purgado_en'),
            'Una importación que arrancó hace un rato sigue leyendo sus filas.',
        );
    }

    /**
     * Nada devuelve a terminal una importación cuyo worker murió a mitad. Si
     * las colgadas no entraran en la purga, su archivo se quedaría en la base
     * para siempre, que es exactamente lo que esto existe para impedir. Un mes
     * después, ningún worker va a volver a por ella.
     */
    public function test_una_importacion_colgada_en_procesando_tambien_caduca(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $colgada = $this->importacionCon($proyecto, $this->crearSupervisor($proyecto)->id, 'procesando', null);

        DB::table('importaciones')->where('id', $colgada)->update([
            'iniciado_en' => Carbon::now()->subDays(60),
            'creada_en' => Carbon::now()->subDays(60),
        ]);

        $this->artisan('importaciones:purgar-payloads')->assertSuccessful();

        $this->assertSame('{}', (string) DB::table('importacion_filas')->where('importacion_id', $colgada)->value('payload'));
        $this->assertNotNull(DB::table('importaciones')->where('id', $colgada)->value('payload_purgado_en'));
    }

    public function test_depurar_no_mueve_la_fecha_de_la_fila(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $importacionId = $this->importacionCon($proyecto, $this->crearSupervisor($proyecto)->id, 'completada', Carbon::now()->subDays(45));

        DB::table('importacion_filas')->where('importacion_id', $importacionId)->update(['actualizada_en' => '2026-01-02 03:04:05']);

        $this->artisan('importaciones:purgar-payloads')->assertSuccessful();

        $this->assertSame(
            '2026-01-02 03:04:05',
            (string) DB::table('importacion_filas')->where('importacion_id', $importacionId)->value('actualizada_en'),
            'Depurar no es editar la fila: la fecha diría que alguien la tocó.',
        );
    }

    public function test_el_simulacro_cuenta_y_no_escribe(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $importacionId = $this->importacionCon($proyecto, $this->crearSupervisor($proyecto)->id, 'completada', Carbon::now()->subDays(45));

        $this->artisan('importaciones:purgar-payloads --dry-run')
            ->expectsOutputToContain('1 importaciones, 1 filas se depurarían')
            ->assertSuccessful();

        $this->assertStringContainsString('8-990-429', (string) DB::table('importacion_filas')->where('importacion_id', $importacionId)->value('payload'));
        $this->assertNull(DB::table('importaciones')->where('id', $importacionId)->value('payload_purgado_en'));
    }

    public function test_la_retencion_se_puede_acortar_desde_la_linea_de_comandos(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $importacionId = $this->importacionCon($proyecto, $this->crearSupervisor($proyecto)->id, 'completada', Carbon::now()->subDays(3));

        $this->artisan('importaciones:purgar-payloads --dias=1')->assertSuccessful();

        $this->assertSame('{}', (string) DB::table('importacion_filas')->where('importacion_id', $importacionId)->value('payload'));
    }

    public function test_una_importacion_depurada_ya_no_ofrece_ni_entrega_sus_filas_rechazadas(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);
        $importacionId = $this->importacionCon($proyecto, $supervisor->id, 'completada', Carbon::now()->subDays(45));

        DB::table('importaciones')->where('id', $importacionId)->update(['invalidas' => 1]);
        DB::table('importacion_filas')->where('importacion_id', $importacionId)->update(['estado' => 'invalida']);
        $publicId = (string) DB::table('importaciones')->where('id', $importacionId)->value('public_id');
        $url = route('proyectos.importaciones.rechazadas', ['proyecto_id' => $proyecto->id, 'importacion' => $publicId]);

        // Antes de depurar se descarga.
        $this->actingAs($supervisor)->get($url)->assertOk();

        $this->artisan('importaciones:purgar-payloads')->assertSuccessful();

        $this->actingAs($supervisor)->get($url)->assertStatus(410);
    }

    public function test_barre_del_disco_las_subidas_que_nadie_proceso(): void
    {
        Storage::fake('local');

        Storage::disk('local')->put('livewire-tmp/viejo.csv', "cedula\n8-1-1\n");
        Storage::disk('local')->put('livewire-tmp/reciente.csv', "cedula\n8-2-2\n");
        touch(Storage::disk('local')->path('livewire-tmp/viejo.csv'), Carbon::now()->subDay()->timestamp);

        $this->artisan('importaciones:purgar-subidas-temporales')->assertSuccessful();

        Storage::disk('local')->assertMissing('livewire-tmp/viejo.csv');
        Storage::disk('local')->assertExists('livewire-tmp/reciente.csv');
    }

    /**
     * Una importación con una fila cargada, en el estado y con la fecha de fin
     * que se le digan.
     */
    private function importacionCon(stdClass $proyecto, int $usuarioId, string $estado, ?Carbon $terminadaEn): int
    {
        $importacionId = (int) DB::table('importaciones')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'tipo_entidad' => 'caso_cobranza',
            'modo' => 'upsert',
            'estado' => $estado,
            'usuario_id' => $usuarioId,
            'nombre_archivo' => 'cartera.csv',
            'total_filas' => 1,
            'terminado_en' => $terminadaEn,
            'creada_en' => $terminadaEn ?? Carbon::now(),
        ]);

        DB::table('importacion_filas')->insert([
            'importacion_id' => $importacionId,
            'proyecto_id' => $proyecto->id,
            'numero_fila' => 1,
            'estado' => 'procesada',
            'payload' => json_encode(['identificacion' => '8-990-429', 'nombres' => 'Ana'], JSON_THROW_ON_ERROR),
            'mensaje_error' => 'motivo cualquiera',
        ]);

        return $importacionId;
    }
}
