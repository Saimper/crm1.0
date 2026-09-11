<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\CamposPersonalizados;

use App\Modules\CamposPersonalizados\Application\Services\ServicioCamposPersonalizados;
use App\Modules\CamposPersonalizados\Domain\Exceptions\CambioDeTipoNoPermitido;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Recuperar los valores que un cambio de tipo dejó atrás, y cerrar la puerta.
 *
 * `campos:recolocar-valores` mueve los valores a la columna que les toca cuando
 * el tipo declarado ya es el bueno. `campos:convertir-tipo` cambia el tipo y
 * mueve los valores a la vez. Y la UI deja de poder cambiar el tipo de un campo
 * que ya tiene valores, que es como se produjo el destrozo.
 */
final class RecolocarValoresTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_recoloca_importes_que_se_quedaron_en_la_columna_de_texto(): void
    {
        $campoId = $this->campoConValores('moneda', ['390.00', '1,234.56', '8.00']);

        $this->artisan('campos:recolocar-valores', ['campo' => $campoId])->assertSuccessful();

        $this->assertSame(0, DB::table('valores_campo_personalizado')
            ->where('campo_personalizado_id', $campoId)->whereNotNull('valor_texto_corto')->count());
        $this->assertSame(3, DB::table('valores_campo_personalizado')
            ->where('campo_personalizado_id', $campoId)->whereNotNull('valor_moneda_monto')->count());
        $this->assertSame('1234.560', DB::table('valores_campo_personalizado')
            ->where('campo_personalizado_id', $campoId)->where('valor_moneda_monto', '1234.56')->value('valor_moneda_monto'));
        $this->assertSame('USD', DB::table('valores_campo_personalizado')
            ->where('campo_personalizado_id', $campoId)->value('valor_moneda_codigo'));
    }

    /**
     * El caso que destapó el comando en producción: un campo declarado `moneda`
     * cuyos valores son fechas. Mover eso sería inventarse importes.
     */
    public function test_se_niega_a_recolocar_valores_que_no_encajan_en_el_tipo(): void
    {
        $campoId = $this->campoConValores('moneda', ['2026-08-30', '2026-07-08']);

        $this->artisan('campos:recolocar-valores', ['campo' => $campoId])->assertFailed();

        $this->assertSame(2, DB::table('valores_campo_personalizado')
            ->where('campo_personalizado_id', $campoId)->whereNotNull('valor_texto_corto')->count());
    }

    public function test_no_recoloca_identificadores_con_ceros_a_la_izquierda(): void
    {
        $campoId = $this->campoConValores('numero_entero', ['0012345', '99']);

        $this->artisan('campos:recolocar-valores', ['campo' => $campoId])->assertFailed();

        $this->assertSame(2, DB::table('valores_campo_personalizado')
            ->where('campo_personalizado_id', $campoId)->whereNotNull('valor_texto_corto')->count());
    }

    public function test_dry_run_no_toca_nada(): void
    {
        $campoId = $this->campoConValores('moneda', ['390.00']);

        $this->artisan('campos:recolocar-valores', ['campo' => $campoId, '--dry-run' => true])->assertSuccessful();

        $this->assertSame('390.00', DB::table('valores_campo_personalizado')
            ->where('campo_personalizado_id', $campoId)->value('valor_texto_corto'));
    }

    /**
     * `campos:convertir-tipo` ya no exige que el tipo declarado sea texto: lo que
     * exige es que los valores sigan en una columna de texto. Es la diferencia
     * que hacía irreparable un campo mal tipado desde la UI.
     */
    public function test_convertir_tipo_arregla_un_campo_mal_declarado(): void
    {
        $campoId = $this->campoConValores('moneda', ['2026-08-30', '2026-07-08']);

        $this->artisan('campos:convertir-tipo', ['campo' => $campoId, 'tipo' => 'fecha'])->assertSuccessful();

        $this->assertSame('fecha', DB::table('campos_personalizados')->where('id', $campoId)->value('tipo'));
        $this->assertSame(2, DB::table('valores_campo_personalizado')
            ->where('campo_personalizado_id', $campoId)->whereNotNull('valor_fecha')->count());
    }

    public function test_la_ui_no_puede_cambiar_el_tipo_de_un_campo_con_valores(): void
    {
        $campoId = $this->campoConValores('texto_corto', ['390.00']);

        $this->expectException(CambioDeTipoNoPermitido::class);

        $this->app->make(ServicioCamposPersonalizados::class)->garantizarTipoMutable($campoId, 'moneda');
    }

    public function test_un_campo_sin_valores_si_cambia_de_tipo(): void
    {
        $campoId = $this->campoConValores('texto_corto', []);

        $this->app->make(ServicioCamposPersonalizados::class)->garantizarTipoMutable($campoId, 'moneda');

        $this->assertTrue(true, 'no lanzó');
    }

    /** @param list<string> $valores */
    private function campoConValores(string $tipo, array $valores): int
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);

        $campoId = (int) DB::table('campos_personalizados')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'ambito' => 'caso',
            'ambito_id' => $cartera->id,
            'codigo' => 'dato',
            'etiqueta' => 'Dato',
            'tipo' => $tipo,
            'obligatorio' => false,
            'activo' => true,
            'orden' => 0,
            'reglas' => json_encode([]),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        foreach ($valores as $i => $valor) {
            DB::table('valores_campo_personalizado')->insert([
                'campo_personalizado_id' => $campoId,
                'entidad_id' => 1000 + $i,
                'valor_texto_corto' => $valor,
                'creada_en' => Carbon::now(),
                'actualizada_en' => Carbon::now(),
            ]);
        }

        return $campoId;
    }
}
