<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\CamposPersonalizados;

use App\Modules\CamposPersonalizados\Application\Services\ServicioCamposPersonalizados;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\AmbitoCampo;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Serialización de valores de campos personalizados (ámbito caso) para el
 * writeback CRM→ViciDial: cada tipo debe convertirse a un string plano,
 * resolviendo selección→etiqueta y moneda→monto.
 */
final class SerializadorWritebackTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_serializa_todos_los_tipos_a_string(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $entidadId = 555;

        $texto = $this->campo($proyecto->id, $cartera->id, 'texto_corto', 'ref');
        $entero = $this->campo($proyecto->id, $cartera->id, 'numero_entero', 'edad');
        $bool = $this->campo($proyecto->id, $cartera->id, 'booleano', 'vip');
        $moneda = $this->campo($proyecto->id, $cartera->id, 'moneda', 'saldo');
        $unica = $this->campo($proyecto->id, $cartera->id, 'seleccion_unica', 'estado');
        $multi = $this->campo($proyecto->id, $cartera->id, 'seleccion_multiple', 'tags');
        $vacio = $this->campo($proyecto->id, $cartera->id, 'texto_corto', 'sin_valor');

        $opActivo = $this->opcion($unica, 'a', 'Activo');
        $opRojo = $this->opcion($multi, 't1', 'Rojo');
        $opVerde = $this->opcion($multi, 't2', 'Verde');

        $this->valor($texto, $entidadId, ['valor_texto_corto' => 'ABC-1']);
        $this->valor($entero, $entidadId, ['valor_numero_entero' => 30]);
        $this->valor($bool, $entidadId, ['valor_booleano' => true]);
        $this->valor($moneda, $entidadId, ['valor_moneda_monto' => '1500.50', 'valor_moneda_codigo' => 'USD']);
        $this->valor($unica, $entidadId, ['valor_opcion_id' => $opActivo]);
        $this->valor($multi, $entidadId, ['valor_opciones_ids' => json_encode([$opRojo, $opVerde])]);
        $this->valor($vacio, $entidadId, ['valor_texto_corto' => null]);

        $out = app(ServicioCamposPersonalizados::class)->valoresSerializadosParaWriteback(
            (int) $proyecto->id,
            AmbitoCampo::CASO,
            (int) $cartera->id,
            $entidadId,
        );

        $this->assertSame('ABC-1', $out['ref']);
        $this->assertSame('30', $out['edad']);
        $this->assertSame('1', $out['vip']);
        $this->assertSame('1500.50', $out['saldo']);
        $this->assertSame('Activo', $out['estado']);          // seleccion_unica → etiqueta
        $this->assertSame('Rojo, Verde', $out['tags']);       // seleccion_multiple → etiquetas
        $this->assertArrayNotHasKey('sin_valor', $out);       // null/vacío descartado
    }

    public function test_etiquetas_de_campos_devuelve_codigo_etiqueta(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $this->campo($proyecto->id, $cartera->id, 'texto_corto', 'saldo', 'Saldo Actual');

        $out = app(ServicioCamposPersonalizados::class)->etiquetasDeCampos(
            (int) $proyecto->id,
            AmbitoCampo::CASO,
            (int) $cartera->id,
            ['saldo', 'inexistente'],
        );

        $this->assertSame(['saldo' => 'Saldo Actual'], $out);
    }

    private function campo(int $proyectoId, int $carteraId, string $tipo, string $codigo, string $etiqueta = 'Campo'): int
    {
        return (int) DB::table('campos_personalizados')->insertGetId([
            'proyecto_id' => $proyectoId,
            'ambito' => 'caso',
            'ambito_id' => $carteraId,
            'tipo' => $tipo,
            'codigo' => $codigo,
            'etiqueta' => $etiqueta,
            'obligatorio' => false,
            'activo' => true,
            'orden' => 1,
            'creada_en' => now(),
            'actualizada_en' => now(),
        ]);
    }

    private function opcion(int $campoId, string $codigo, string $etiqueta): int
    {
        return (int) DB::table('opciones_campo_personalizado')->insertGetId([
            'campo_personalizado_id' => $campoId,
            'codigo' => $codigo,
            'etiqueta' => $etiqueta,
            'activo' => true,
            'orden' => 1,
            'creada_en' => now(),
            'actualizada_en' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $cols
     */
    private function valor(int $campoId, int $entidadId, array $cols): void
    {
        DB::table('valores_campo_personalizado')->insert(array_merge([
            'campo_personalizado_id' => $campoId,
            'entidad_id' => $entidadId,
            'creada_en' => now(),
            'actualizada_en' => now(),
        ], $cols));
    }
}
