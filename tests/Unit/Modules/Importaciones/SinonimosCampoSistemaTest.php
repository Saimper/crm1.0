<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Importaciones;

use App\Modules\Importaciones\Domain\Catalogo\SinonimosCampoSistema;
use PHPUnit\Framework\TestCase;

/**
 * F41: si la columna del archivo ya existe en el sistema, el importador debe
 * reconocerla sola. Los casos de esta prueba salen de la base real del cliente
 * (Banco Azteca), que es la que dejó personas sin nombre y casos sin saldo.
 */
final class SinonimosCampoSistemaTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function encabezadosReales(): array
    {
        return [
            'cedula' => ['CEDULA', 'identificacion'],
            'nombre del titular' => ['NOMBRE DEL TITULAR', 'nombres'],
            'saldo total' => ['SALDO TOTAL', 'saldo_total'],
            'capital' => ['CAPITAL', 'saldo_capital'],
            'intereses' => ['INTERESES', 'saldo_interes'],
            'cuota de pago' => ['CUOTA DE PAGO', 'cuota_mensual'],
            'dias en atraso' => ['DIAS EN ATRASO', 'dias_mora'],
        ];
    }

    /**
     * @dataProvider encabezadosReales
     */
    public function test_reconoce_los_encabezados_de_la_base_del_cliente(string $encabezado, string $esperado): void
    {
        $this->assertSame($esperado, SinonimosCampoSistema::buscar($encabezado));
    }

    public function test_es_indiferente_a_mayusculas_espacios_y_guiones(): void
    {
        foreach (['NOMBRE DEL TITULAR', 'nombre_del_titular', 'Nombre-Del-Titular', 'nombredeltitular'] as $variante) {
            $this->assertSame('nombres', SinonimosCampoSistema::buscar($variante), "Falló con: {$variante}");
        }
    }

    public function test_el_codigo_canonico_siempre_se_reconoce_a_si_mismo(): void
    {
        foreach (array_keys(SinonimosCampoSistema::MAPA) as $codigo) {
            $this->assertSame($codigo, SinonimosCampoSistema::buscar($codigo));
        }
    }

    public function test_columna_desconocida_no_mapea(): void
    {
        foreach (['SEGMENTO', 'PANAPASS', 'GESTOR', 'columna_17'] as $ajena) {
            $this->assertNull(SinonimosCampoSistema::buscar($ajena));
        }
    }

    public function test_ningun_sinonimo_esta_repetido_entre_campos(): void
    {
        $vistos = [];

        foreach (SinonimosCampoSistema::MAPA as $codigo => $sinonimos) {
            foreach ($sinonimos as $sinonimo) {
                $normalizado = SinonimosCampoSistema::normalizar($sinonimo);
                $this->assertArrayNotHasKey(
                    $normalizado,
                    $vistos,
                    "El sinónimo «{$sinonimo}» está en {$codigo} y en ".($vistos[$normalizado] ?? '?').'.',
                );
                $vistos[$normalizado] = $codigo;
            }
        }
    }
}
