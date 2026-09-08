<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Lo que ya entró roto antes de que el lector corrigiera al leer: 26 personas
 * y 29 valores de campos personalizados en el proyecto 8.
 */
final class RepararEncodingCommandTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    private const REPARABLE = "GONZ\xC3\x83\xC2\x81LEZ";

    private const IRREPARABLE = "CEDE\xC3\x83\xC3\x91O";

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_sin_proyecto_ni_todos_no_hace_nada_y_lo_dice(): void
    {
        $this->artisan('importaciones:reparar-encoding')
            ->expectsOutputToContain('--proyecto')
            ->assertFailed();
    }

    public function test_el_dry_run_cuenta_pero_no_escribe(): void
    {
        [$proyecto, $personaRota] = $this->escenario();

        $this->artisan('importaciones:reparar-encoding', ['--proyecto' => (int) $proyecto->id, '--dry-run' => true])
            ->expectsOutputToContain('Simulación')
            ->assertSuccessful();

        $this->assertSame(self::REPARABLE, (string) DB::table('personas')->where('id', $personaRota)->value('nombres'));
    }

    public function test_una_pasada_repara_personas_y_valores_del_proyecto_y_no_toca_otro(): void
    {
        [$proyecto, $personaRota, $valorId] = $this->escenario();
        $otroProyecto = $this->crearProyectoCobranza();
        $personaAjena = (int) $this->crearPersonaEn($otroProyecto)->id;
        DB::table('personas')->where('id', $personaAjena)->update(['nombres' => "DOM\xC3\x83\xC2\x8DNGUEZ"]);

        $this->artisan('importaciones:reparar-encoding', ['--proyecto' => (int) $proyecto->id])
            ->expectsOutputToContain('Listo.')
            ->assertSuccessful();

        $persona = DB::table('personas')->where('id', $personaRota)->first();
        $this->assertSame('GONZÁLEZ', (string) $persona->nombres);
        $this->assertSame('ñ', (string) DB::table('valores_campo_personalizado')->where('id', $valorId)->value('valor_texto_corto'));
        $this->assertSame("DOM\xC3\x83\xC2\x8DNGUEZ", (string) DB::table('personas')->where('id', $personaAjena)->value('nombres'), 'El otro proyecto no se toca.');
    }

    public function test_reparar_no_mueve_la_fecha_de_actualizacion(): void
    {
        [$proyecto, $personaRota] = $this->escenario();
        $antes = (string) DB::table('personas')->where('id', $personaRota)->value('actualizada_en');

        Carbon::setTestNow(Carbon::now()->addDay());
        $this->artisan('importaciones:reparar-encoding', ['--proyecto' => (int) $proyecto->id])->assertSuccessful();
        Carbon::setTestNow();

        $this->assertSame($antes, (string) DB::table('personas')->where('id', $personaRota)->value('actualizada_en'));
    }

    public function test_las_irreparables_se_cuentan_y_no_se_modifican(): void
    {
        [$proyecto] = $this->escenario();
        $irreparable = (int) $this->crearPersonaEn($proyecto)->id;
        DB::table('personas')->where('id', $irreparable)->update(['nombres' => 'RUBÉN', 'apellidos' => self::IRREPARABLE]);

        Log::spy();

        $this->artisan('importaciones:reparar-encoding', ['--proyecto' => (int) $proyecto->id])
            ->expectsOutputToContain((string) $irreparable)
            ->assertSuccessful();

        $this->assertSame(self::IRREPARABLE, (string) DB::table('personas')->where('id', $irreparable)->value('apellidos'));

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(fn (string $mensaje, array $contexto): bool => $contexto['proyecto_id'] === (int) $proyecto->id
                && $contexto['tablas']['personas']['reparadas'] === 1
                && $contexto['tablas']['personas']['irreparables'] === 1
                && $contexto['tablas']['valores_campo_personalizado']['reparadas'] === 1);
    }

    /**
     * Un proyecto con una persona reparable y un valor de campo personalizado
     * reparable (colgado de un caso de su cartera).
     *
     * @return array{0: stdClass, 1: int, 2: int}
     */
    private function escenario(): array
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);
        DB::table('personas')->where('id', $persona->id)->update(['nombres' => self::REPARABLE]);

        $casoId = $this->crearCasoEn($proyecto, ['cartera' => $cartera, 'persona' => $persona]);

        $campoId = (int) DB::table('campos_personalizados')->insertGetId([
            'proyecto_id' => $proyecto->id, 'ambito' => 'caso', 'ambito_id' => $cartera->id,
            'codigo' => 'observacion', 'etiqueta' => 'Observación', 'tipo' => 'texto_corto',
            'obligatorio' => false, 'activo' => true, 'orden' => 0, 'reglas' => json_encode([]),
            'creada_en' => Carbon::now(), 'actualizada_en' => Carbon::now(),
        ]);

        $valorId = (int) DB::table('valores_campo_personalizado')->insertGetId([
            'campo_personalizado_id' => $campoId, 'entidad_id' => $casoId,
            'valor_texto_corto' => 'Ã±',
            'creada_en' => Carbon::now(), 'actualizada_en' => Carbon::now(),
        ]);

        return [$proyecto, (int) $persona->id, $valorId];
    }
}
