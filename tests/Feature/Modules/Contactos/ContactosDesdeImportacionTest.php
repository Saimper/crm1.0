<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Contactos;

use App\Modules\Contactos\Domain\Contracts\AltaContactosEnLote;
use App\Modules\Contactos\Domain\ValueObjects\ExtractorDeContactos;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Dar de alta contactos a partir de las celdas de texto de la importación.
 *
 * «Contacto usado» salía siempre vacío porque la tabla `contactos` estaba vacía
 * en los tres proyectos: 14.692 personas, cero contactos. Los teléfonos están,
 * pero en campos de texto que la importación creó como texto.
 */
final class ContactosDesdeImportacionTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_da_de_alta_los_telefonos_de_una_celda(): void
    {
        [$proyecto, $personaId] = $this->escenario();
        $extractor = new ExtractorDeContactos;

        $n = $this->app->make(AltaContactosEnLote::class)->alta(
            (int) $proyecto->id,
            $personaId,
            $extractor->telefonos('61750650   65976897   66214303'),
            'importacion',
        );

        $this->assertSame(3, $n);
        $this->assertDatabaseHas('contactos', [
            'persona_id' => $personaId, 'tipo' => 'telefono', 'valor' => '61750650', 'origen' => 'importacion',
        ]);
    }

    /** Reimportar el mismo fichero no puede duplicar contactos. */
    public function test_la_segunda_vez_no_duplica(): void
    {
        [$proyecto, $personaId] = $this->escenario();
        $extractor = new ExtractorDeContactos;
        $alta = $this->app->make(AltaContactosEnLote::class);

        $alta->alta((int) $proyecto->id, $personaId, $extractor->telefonos('61750650 65976897'), 'importacion');
        $segunda = $alta->alta((int) $proyecto->id, $personaId, $extractor->telefonos('61750650 65976897'), 'importacion');

        $this->assertSame(0, $segunda);
        $this->assertSame(2, DB::table('contactos')->where('persona_id', $personaId)->count());
    }

    /** El primer teléfono de quien no tenía ninguno pasa a ser el principal. */
    public function test_el_primero_queda_como_principal(): void
    {
        [$proyecto, $personaId] = $this->escenario();

        $this->app->make(AltaContactosEnLote::class)->alta(
            (int) $proyecto->id, $personaId,
            (new ExtractorDeContactos)->telefonos('61750650 65976897'),
            'importacion',
        );

        $this->assertSame(1, DB::table('contactos')
            ->where('persona_id', $personaId)->where('es_principal', true)->count());
    }

    public function test_las_referencias_traen_el_nombre_de_quien_contesta(): void
    {
        [$proyecto, $personaId] = $this->escenario();

        $this->app->make(AltaContactosEnLote::class)->alta(
            (int) $proyecto->id, $personaId,
            (new ExtractorDeContactos)->referencias('GUSTAVO GARRIDO(61750650), MIRIAM ANGEL BILL (65976897)'),
            'importacion',
        );

        $this->assertDatabaseHas('contactos', [
            'persona_id' => $personaId, 'valor' => '61750650', 'etiqueta' => 'GUSTAVO GARRIDO',
        ]);
    }

    /** Multi-tenancy (§12): los contactos nacen dentro del proyecto de la persona. */
    public function test_los_contactos_quedan_en_el_proyecto_de_la_persona(): void
    {
        [$proyectoA, $personaA] = $this->escenario();
        [$proyectoB] = $this->escenario();

        $this->app->make(AltaContactosEnLote::class)->alta(
            (int) $proyectoA->id, $personaA,
            (new ExtractorDeContactos)->telefonos('61750650'),
            'importacion',
        );

        $this->assertSame(1, DB::table('contactos')->where('proyecto_id', $proyectoA->id)->count());
        $this->assertSame(0, DB::table('contactos')->where('proyecto_id', $proyectoB->id)->count());
    }

    /** El comando de relleno recorre lo que ya está cargado, y sólo si se lo piden. */
    public function test_el_comando_de_relleno_saca_contactos_de_un_campo_existente(): void
    {
        [$proyecto, $personaId, $casoId, $cartera] = $this->escenario();

        $campoId = (int) DB::table('campos_personalizados')->insertGetId([
            'proyecto_id' => $proyecto->id, 'ambito' => 'caso', 'ambito_id' => $cartera->id,
            'codigo' => 'telefonos', 'etiqueta' => 'Telefonos', 'tipo' => 'texto_corto',
            'obligatorio' => false, 'activo' => true, 'orden' => 0, 'reglas' => json_encode([]),
            'creada_en' => Carbon::now(), 'actualizada_en' => Carbon::now(),
        ]);

        DB::table('valores_campo_personalizado')->insert([
            'campo_personalizado_id' => $campoId, 'entidad_id' => $casoId,
            'valor_texto_corto' => '61750650   65976897   000000',
            'creada_en' => Carbon::now(), 'actualizada_en' => Carbon::now(),
        ]);

        $this->artisan('contactos:extraer-de-campos', [
            '--proyecto' => (int) $proyecto->id,
            '--telefono' => ['telefonos'],
        ])->assertSuccessful();

        $this->assertSame(2, DB::table('contactos')->where('persona_id', $personaId)->count(),
            'los dos válidos; el 000000 se descarta');
    }

    public function test_el_dry_run_del_comando_no_escribe(): void
    {
        [$proyecto, $personaId, $casoId, $cartera] = $this->escenario();

        $campoId = (int) DB::table('campos_personalizados')->insertGetId([
            'proyecto_id' => $proyecto->id, 'ambito' => 'caso', 'ambito_id' => $cartera->id,
            'codigo' => 'telefonos', 'etiqueta' => 'Telefonos', 'tipo' => 'texto_corto',
            'obligatorio' => false, 'activo' => true, 'orden' => 0, 'reglas' => json_encode([]),
            'creada_en' => Carbon::now(), 'actualizada_en' => Carbon::now(),
        ]);
        DB::table('valores_campo_personalizado')->insert([
            'campo_personalizado_id' => $campoId, 'entidad_id' => $casoId,
            'valor_texto_corto' => '61750650',
            'creada_en' => Carbon::now(), 'actualizada_en' => Carbon::now(),
        ]);

        $this->artisan('contactos:extraer-de-campos', [
            '--proyecto' => (int) $proyecto->id,
            '--telefono' => ['telefonos'],
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(0, DB::table('contactos')->count());
    }

    public function test_el_comando_exige_proyecto_y_algun_campo(): void
    {
        $this->artisan('contactos:extraer-de-campos')->assertFailed();
        $this->artisan('contactos:extraer-de-campos', ['--proyecto' => 1])->assertFailed();
    }

    /** @return array{0: stdClass, 1: int, 2: int, 3: stdClass} */
    private function escenario(): array
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto, 'ABIERTO');
        $persona = $this->crearPersonaEn($proyecto);

        $casoId = (int) DB::table('casos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id, 'cartera_id' => $cartera->id, 'persona_id' => $persona->id,
            'tipo_caso' => 'cobranza', 'estado_caso_id' => $estado->id,
            'fecha_ingreso' => Carbon::today(),
            'creada_en' => Carbon::now(), 'actualizada_en' => Carbon::now(),
        ]);

        return [$proyecto, (int) $persona->id, $casoId, $cartera];
    }
}
