<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Cobranza;

use App\Modules\Cobranza\Domain\ValueObjects\DiasMora;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Una mora rescatada de un campo personalizado se ancla al día en que AQUEL
 * archivo la afirmó, nunca a hoy: anclarla a hoy haría que el envejecimiento
 * partiera de una foto vieja como si fuera reciente.
 */
final class RescateDiasMoraTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_el_rescate_ancla_la_mora_al_dia_en_que_el_archivo_la_afirmo(): void
    {
        $mandante = $this->crearMandante();
        DB::table('mandantes')->where('id', $mandante->id)->update(['zona_horaria' => 'America/Panama']);
        $proyecto = $this->crearProyectoCobranza($mandante);

        // El valor se escribió a las 02:30 UTC del 3: en Panamá era la noche del 2.
        $casoId = $this->cuentaConMoraEnCampoPersonalizado($proyecto, '45', '2026-09-03 02:30:00');

        Carbon::setTestNow(Carbon::parse('2026-09-10 12:00:00', 'UTC'));
        $this->artisan('importaciones:rescatar-campos-nativos', ['--proyecto' => $proyecto->id])->assertSuccessful();

        $fila = $this->fila($casoId);
        $this->assertSame(45, (int) $fila->dias_mora);
        $this->assertSame('2026-09-02', (string) $fila->dias_mora_actualizado_en, 'El día del archivo, en el calendario del cliente.');
        $this->assertSame('2026-09-02', (string) $fila->dias_mora_confirmado_en, 'Y es una fuente: también confirma.');
    }

    /**
     * El upsert de valores conserva `creada_en` de la primera importación. Con
     * un archivo semanal reimportado, anclar a `creada_en` daba el valor del
     * último archivo con la fecha del primero, y el cron nocturno sumaba
     * encima las semanas que la cifra ya traía dentro.
     */
    public function test_el_rescate_ancla_al_ultimo_archivo_que_toco_el_valor_y_no_al_primero(): void
    {
        $mandante = $this->crearMandante();
        DB::table('mandantes')->where('id', $mandante->id)->update(['zona_horaria' => 'America/Panama']);
        $proyecto = $this->crearProyectoCobranza($mandante);

        $casoId = $this->cuentaConMoraEnCampoPersonalizado($proyecto, '45', '2026-06-01 02:30:00');

        // Tres meses después el mismo archivo vuelve: el valor se actualiza y
        // `creada_en` se queda donde estaba.
        DB::table('valores_campo_personalizado')
            ->where('entidad_id', $casoId)
            ->update(['actualizada_en' => '2026-09-03 02:30:00']);

        Carbon::setTestNow(Carbon::parse('2026-09-10 12:00:00', 'UTC'));
        $this->artisan('importaciones:rescatar-campos-nativos', ['--proyecto' => $proyecto->id])->assertSuccessful();

        $fila = $this->fila($casoId);
        $this->assertSame('2026-09-02', (string) $fila->dias_mora_actualizado_en, 'La fecha del último archivo, no la del primero.');
        $this->assertSame('2026-09-02', (string) $fila->dias_mora_confirmado_en);
    }

    public function test_el_rescate_no_copia_una_mora_que_el_dominio_rechaza(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $casoId = $this->cuentaConMoraEnCampoPersonalizado($proyecto, (string) (DiasMora::MAXIMO_RAZONABLE + 1), '2026-09-03 02:30:00');

        $this->artisan('importaciones:rescatar-campos-nativos', ['--proyecto' => $proyecto->id])->assertSuccessful();

        // Copiarla dejaría una fila que el VO no puede hidratar: la Vista de
        // Trabajo del caso reventaría en vez de enseñar una mora dudosa.
        $fila = $this->fila($casoId);
        $this->assertNull($fila->dias_mora);
        $this->assertNull($fila->dias_mora_actualizado_en);
        $this->assertNull($fila->dias_mora_confirmado_en);
    }

    /** El CP se llama como uno de los sinónimos de `dias_mora` para que el rescate lo reconozca. */
    private function cuentaConMoraEnCampoPersonalizado(stdClass $proyecto, string $valor, string $creadaEn): int
    {
        $cartera = $this->crearCarteraEn($proyecto);
        $casoId = $this->crearCasoEn($proyecto, ['cartera' => $cartera, 'tipo_caso' => 'cobranza']);

        DB::table('casos_cobranza')->insert([
            'caso_id' => $casoId,
            'proyecto_id' => $proyecto->id,
            'numero_prestamo' => "P-{$casoId}",
            'dias_mora' => null,
        ]);

        $campoId = (int) DB::table('campos_personalizados')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'ambito' => 'caso',
            'ambito_id' => $cartera->id,
            'codigo' => 'dias_atraso',
            'etiqueta' => 'Días de atraso',
            'tipo' => 'texto_corto',
            'obligatorio' => false,
            'activo' => true,
            'orden' => 10,
            'reglas' => json_encode([]),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        DB::table('valores_campo_personalizado')->insert([
            'campo_personalizado_id' => $campoId,
            'entidad_id' => $casoId,
            'valor_texto_corto' => $valor,
            'creada_en' => $creadaEn,
            'actualizada_en' => $creadaEn,
        ]);

        return $casoId;
    }

    private function fila(int $casoId): stdClass
    {
        /** @var stdClass $fila */
        $fila = DB::table('casos_cobranza')->where('caso_id', $casoId)->first();

        return $fila;
    }
}
