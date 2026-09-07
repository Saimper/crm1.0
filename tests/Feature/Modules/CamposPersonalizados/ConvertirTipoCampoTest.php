<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\CamposPersonalizados;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Las importaciones anteriores a F41 crearon todo como texto: en producción los
 * 123 campos del proyecto de cobranza son `texto_corto`, saldos y fechas
 * incluidos, y por eso los reportes ordenan "100" antes que "99".
 *
 * Convertir es fácil de hacer mal: un campo con ceros a la izquierda es un
 * identificador y convertirlo lo destruye; una fecha dd/mm es indistinguible de
 * mm/dd y adivinar corrompe. Estos tests fijan esas dos defensas.
 */
final class ConvertirTipoCampoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** @param list<string> $valores */
    private function crearCampoTexto(stdClass $proyecto, string $codigo, array $valores): int
    {
        $campoId = (int) DB::table('campos_personalizados')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'ambito' => 'caso',
            'ambito_id' => 1,
            'codigo' => $codigo,
            'etiqueta' => $codigo,
            'tipo' => 'texto_corto',
            'obligatorio' => false,
            'activo' => true,
        ]);

        foreach ($valores as $i => $v) {
            DB::table('valores_campo_personalizado')->insert([
                'campo_personalizado_id' => $campoId,
                'entidad_id' => $i + 1,
                'valor_texto_corto' => $v,
            ]);
        }

        return $campoId;
    }

    public function test_convierte_numeros_y_vacia_la_columna_de_texto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $campoId = $this->crearCampoTexto($proyecto, 'saldo', ['1500.50', '99', '100']);

        $this->artisan("campos:convertir-tipo {$campoId} numero_decimal")->assertSuccessful();

        $this->assertSame('numero_decimal', DB::table('campos_personalizados')->where('id', $campoId)->value('tipo'));

        $filas = DB::table('valores_campo_personalizado')->where('campo_personalizado_id', $campoId)->get();
        foreach ($filas as $f) {
            $this->assertNotNull($f->valor_numero_decimal);
            $this->assertNull($f->valor_texto_corto, 'El valor no debe quedar duplicado en dos columnas.');
        }

        // El punto de todo esto: 100 > 99 numéricamente, al revés que como texto.
        $mayor = DB::table('valores_campo_personalizado')->where('campo_personalizado_id', $campoId)
            ->orderByDesc('valor_numero_decimal')->value('valor_numero_decimal');
        $this->assertSame(1500.5, (float) $mayor);
    }

    public function test_se_niega_a_convertir_un_identificador_con_ceros_a_la_izquierda(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $campoId = $this->crearCampoTexto($proyecto, 'de_prestamo', ['0012345', '0098765']);

        $this->artisan("campos:convertir-tipo {$campoId} numero_entero")->assertFailed();

        $this->assertSame('texto_corto', DB::table('campos_personalizados')->where('id', $campoId)->value('tipo'));
        $this->assertSame('0012345', DB::table('valores_campo_personalizado')
            ->where('campo_personalizado_id', $campoId)->orderBy('id')->value('valor_texto_corto'));
    }

    public function test_forzar_permite_convertir_pero_hay_que_pedirlo(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $campoId = $this->crearCampoTexto($proyecto, 'codigo_num', ['0012345']);

        $this->artisan("campos:convertir-tipo {$campoId} numero_entero --forzar")->assertSuccessful();

        $this->assertSame(12345, (int) DB::table('valores_campo_personalizado')
            ->where('campo_personalizado_id', $campoId)->value('valor_numero_entero'));
    }

    public function test_aborta_entero_si_algun_valor_no_encaja(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $campoId = $this->crearCampoTexto($proyecto, 'mixto', ['10', 'no es un numero', '30']);

        $this->artisan("campos:convertir-tipo {$campoId} numero_entero")->assertFailed();

        $this->assertSame(3, DB::table('valores_campo_personalizado')
            ->where('campo_personalizado_id', $campoId)->whereNotNull('valor_texto_corto')->count(),
            'Un fallo parcial no debe dejar el campo a medias.');
    }

    public function test_convierte_fechas_iso(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $campoId = $this->crearCampoTexto($proyecto, 'fecha_pago', ['2026-05-04', '2025-12-31']);

        $this->artisan("campos:convertir-tipo {$campoId} fecha")->assertSuccessful();

        $this->assertSame('fecha', DB::table('campos_personalizados')->where('id', $campoId)->value('tipo'));
        $this->assertSame(2, DB::table('valores_campo_personalizado')
            ->where('campo_personalizado_id', $campoId)->whereNotNull('valor_fecha')->count());
    }

    public function test_rechaza_fechas_ambiguas_dd_mm(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $campoId = $this->crearCampoTexto($proyecto, 'fecha_rara', ['04/05/2026', '31/12/2025']);

        $this->artisan("campos:convertir-tipo {$campoId} fecha")->assertFailed();

        $this->assertSame('texto_corto', DB::table('campos_personalizados')->where('id', $campoId)->value('tipo'));
    }

    public function test_rechaza_una_fecha_que_no_existe(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $campoId = $this->crearCampoTexto($proyecto, 'fecha_falsa', ['2026-02-30']);

        $this->artisan("campos:convertir-tipo {$campoId} fecha")->assertFailed();
    }

    public function test_la_simulacion_no_escribe(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $campoId = $this->crearCampoTexto($proyecto, 'saldo', ['1500.50']);

        $this->artisan("campos:convertir-tipo {$campoId} numero_decimal --dry-run")->assertSuccessful();

        $this->assertSame('texto_corto', DB::table('campos_personalizados')->where('id', $campoId)->value('tipo'));
        $this->assertNotNull(DB::table('valores_campo_personalizado')
            ->where('campo_personalizado_id', $campoId)->value('valor_texto_corto'));
    }

    /**
     * Antes el comando exigía que el tipo declarado fuese texto. Ahora exige que
     * los VALORES sigan en una columna de texto, que no es lo mismo: un campo al
     * que le cambiaron el tipo desde la UI queda declarado con el tipo nuevo y
     * con los valores atrás, y es justo el que hay que poder arreglar.
     */
    public function test_convierte_un_campo_mal_declarado_cuyos_valores_siguen_en_texto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $campoId = $this->crearCampoTexto($proyecto, 'ya_num', ['10']);
        DB::table('campos_personalizados')->where('id', $campoId)->update(['tipo' => 'numero_entero']);

        $this->artisan("campos:convertir-tipo {$campoId} numero_decimal")->assertSuccessful();

        $this->assertSame('numero_decimal', DB::table('campos_personalizados')->where('id', $campoId)->value('tipo'));
        $this->assertSame('10.0000', DB::table('valores_campo_personalizado')
            ->where('campo_personalizado_id', $campoId)->value('valor_numero_decimal'));
    }

    /** Sin valores en texto no hay nada que convertir desde texto. */
    public function test_no_convierte_cuando_los_valores_ya_estan_en_su_columna(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $campoId = $this->crearCampoTexto($proyecto, 'ya_num', ['10']);
        $this->artisan("campos:convertir-tipo {$campoId} numero_entero")->assertSuccessful();

        $this->artisan("campos:convertir-tipo {$campoId} numero_decimal")->assertFailed();
    }
}
