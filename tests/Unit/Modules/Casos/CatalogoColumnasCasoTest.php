<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Casos;

use App\Modules\Casos\Domain\Columnas\CatalogoColumnasCaso;
use App\Modules\Casos\Domain\Columnas\ColumnaCaso;
use PHPUnit\Framework\TestCase;

/**
 * F41: el usuario elige qué columnas ve en el listado de casos. Solo puede elegir
 * claves de este catálogo: nunca aporta SQL.
 */
final class CatalogoColumnasCasoTest extends TestCase
{
    public function test_cobranza_ofrece_cuenta_saldo_y_capital(): void
    {
        $claves = array_keys(CatalogoColumnasCaso::indexadoPorClave('cobranza'));

        foreach (['cuenta', 'saldo_total', 'saldo_capital', 'dias_mora'] as $esperada) {
            $this->assertContains($esperada, $claves);
        }
    }

    public function test_las_columnas_de_otro_tipo_no_estan_disponibles(): void
    {
        $claves = array_keys(CatalogoColumnasCaso::indexadoPorClave('cobranza'));

        $this->assertNotContains('asunto', $claves);
        $this->assertNotContains('codigo_lead', $claves);
    }

    public function test_sanear_descarta_claves_inventadas(): void
    {
        $saneadas = CatalogoColumnasCaso::sanear(
            ['persona', 'saldo_total', 'DROP TABLE casos', 'columna_inexistente'],
            'cobranza',
        );

        $this->assertSame(['persona', 'saldo_total'], $saneadas);
    }

    public function test_sanear_elimina_repetidas_conservando_el_orden(): void
    {
        $saneadas = CatalogoColumnasCaso::sanear(['saldo_total', 'persona', 'saldo_total'], 'cobranza');

        $this->assertSame(['saldo_total', 'persona'], $saneadas);
    }

    public function test_sanear_cae_en_las_por_defecto_si_no_queda_nada_valido(): void
    {
        $this->assertSame(CatalogoColumnasCaso::POR_DEFECTO, CatalogoColumnasCaso::sanear([], 'cobranza'));
        $this->assertSame(CatalogoColumnasCaso::POR_DEFECTO, CatalogoColumnasCaso::sanear(['inventada'], 'cobranza'));
    }

    public function test_las_columnas_por_defecto_existen_en_todos_los_tipos(): void
    {
        foreach (['cobranza', 'cx', 'venta', 'servicio'] as $tipo) {
            $claves = array_keys(CatalogoColumnasCaso::indexadoPorClave($tipo));

            foreach (CatalogoColumnasCaso::POR_DEFECTO as $porDefecto) {
                $this->assertContains($porDefecto, $claves, "Falta {$porDefecto} en {$tipo}.");
            }
        }
    }

    public function test_ninguna_expresion_sql_viene_del_usuario(): void
    {
        foreach (['cobranza', 'cx', 'venta', 'servicio'] as $tipo) {
            foreach (CatalogoColumnasCaso::paraTipoOperacion($tipo) as $columna) {
                $this->assertMatchesRegularExpression(
                    '/^[a-z]+\.[a-z_]+$/',
                    $columna->expresion,
                    "Expresión no calificada simple: {$columna->expresion}",
                );
            }
        }
    }

    public function test_alias_es_estable_y_prefijado(): void
    {
        $columna = new ColumnaCaso('saldo_total', 'Saldo', 'cti.saldo_total', numerica: true);

        $this->assertSame('col_saldo_total', $columna->alias());
    }

    public function test_tabla_cti_por_tipo(): void
    {
        $this->assertSame('casos_cobranza', CatalogoColumnasCaso::tablaCti('cobranza'));
        $this->assertSame('casos_ticket_cx', CatalogoColumnasCaso::tablaCti('cx'));
        $this->assertNull(CatalogoColumnasCaso::tablaCti('desconocido'));
    }
}
