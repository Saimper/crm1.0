<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Importaciones;

use App\Modules\Importaciones\Application\Services\NormalizadorEncoding;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Los bytes son los reales del proyecto 8: 26 personas y 29 valores de campos
 * personalizados con dos patrones distintos, uno reversible y otro no.
 */
final class NormalizadorEncodingTest extends TestCase
{
    /** @return iterable<string, array{0: string, 1: string}> */
    public static function celdasReparables(): iterable
    {
        yield 'Ã + control C1 (GONZÁLEZ)' => ["GONZ\xC3\x83\xC2\x81LEZ", 'GONZÁLEZ'];
        yield 'Ã + control C1 (DOMÍNGUEZ)' => ["DOM\xC3\x83\xC2\x8DNGUEZ", 'DOMÍNGUEZ'];
        yield 'Ã + comilla tipográfica (Ñ)' => ["\xC3\x83\xE2\x80\x98", 'Ñ'];
        yield 'Ã±' => ['Ã±', 'ñ'];
        yield 'Ã©' => ['Ã©', 'é'];
        yield 'â€™ (tres bytes)' => ['â€™', '’'];
        yield 'mezcla en la misma celda' => ['JosÃ© PÃ©rez', 'José Pérez'];
    }

    #[DataProvider('celdasReparables')]
    public function test_deshace_la_doble_codificacion_reversible(string $rota, string $esperada): void
    {
        self::assertSame($esperada, (new NormalizadorEncoding)->repararDobleCodificacion($rota));
    }

    /** @return iterable<string, array{0: string}> */
    public static function celdasQueSeDejanComoEstan(): iterable
    {
        yield 'ÃÑ: el byte de continuación fue sustituido por una Ñ, no se puede deshacer' => ["CEDE\xC3\x83\xC3\x91O"];
        yield 'NÃÑÃÑEZ: dos veces el mismo daño' => ["N\xC3\x83\xC3\x91\xC3\x83\xC3\x91EZ"];
        yield 'Ñ legítima minúscula' => ['Ñuñoa'];
        yield 'Ñ legítima mayúscula' => ['PEÑA'];
        yield 'Ã legítima seguida de una letra ASCII (portugués)' => ['JOÃO'];
        yield 'sin marcas' => ['GONZÁLEZ'];
        yield 'vacía' => [''];
    }

    #[DataProvider('celdasQueSeDejanComoEstan')]
    public function test_nunca_adivina_lo_que_no_es_reversible(string $celda): void
    {
        self::assertSame($celda, (new NormalizadorEncoding)->repararDobleCodificacion($celda));
    }

    public function test_nunca_mapea_a_enie_la_secuencia_ambigua(): void
    {
        // RUBÃÑN es RUBÉN y NÃÑÃÑEZ es NÚÑEZ: mapear «ÃÑ» → «Ñ» acertaría
        // CEDEÑO y corrompería los otros dos en silencio.
        self::assertSame("RUB\xC3\x83\xC3\x91N", (new NormalizadorEncoding)->repararDobleCodificacion("RUB\xC3\x83\xC3\x91N"));
    }

    public function test_un_contenido_en_windows_1252_pasa_a_utf8(): void
    {
        $latin = "PE\xD1A,Jos\xE9";

        self::assertSame('PEÑA,José', (new NormalizadorEncoding)->aUtf8($latin));
    }

    public function test_un_contenido_ya_en_utf8_no_se_toca(): void
    {
        $utf8 = "PEÑA,José,GONZ\xC3\x83\xC2\x81LEZ";

        self::assertSame($utf8, (new NormalizadorEncoding)->aUtf8($utf8));
    }

    public function test_detecta_las_marcas_que_quedan_tras_reparar(): void
    {
        $normalizador = new NormalizadorEncoding;

        self::assertTrue($normalizador->pareceDobleCodificada("CEDE\xC3\x83\xC3\x91O"));
        self::assertFalse($normalizador->pareceDobleCodificada('GONZÁLEZ'));
    }
}
