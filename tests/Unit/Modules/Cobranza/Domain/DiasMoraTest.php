<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Cobranza\Domain;

use App\Modules\Cobranza\Domain\Exceptions\DatosCasoCobranzaInvalidos;
use App\Modules\Cobranza\Domain\ValueObjects\DiasMora;
use PHPUnit\Framework\TestCase;

/**
 * Los días de mora tienen un techo de cordura.
 *
 * En la réplica de producción hay cuentas con 6.279 días —más de diecisiete
 * años— y semanas de atraso de 897. No son cuentas antiquísimas: son fechas mal
 * leídas en la importación, y la pantalla las presentaba como ciertas, con un
 * «Más de 15 años» debajo que le daba aire de dato verificado.
 */
final class DiasMoraTest extends TestCase
{
    public function test_acepta_una_mora_normal(): void
    {
        $this->assertSame(90, (new DiasMora(90))->dias);
        $this->assertTrue((new DiasMora(1))->estaEnMora());
        $this->assertFalse((new DiasMora(0))->estaEnMora());
    }

    public function test_rechaza_una_mora_negativa(): void
    {
        $this->expectException(DatosCasoCobranzaInvalidos::class);

        new DiasMora(-1);
    }

    public function test_rechaza_una_mora_de_mas_de_cuarenta_anos(): void
    {
        $this->expectException(DatosCasoCobranzaInvalidos::class);
        $this->expectExceptionMessageMatches('/fecha mal leída/');

        new DiasMora(14601);
    }

    /** El caso real de la réplica: 6.279 días. */
    public function test_seis_mil_dias_sigue_siendo_admisible_aunque_huela_mal(): void
    {
        $this->assertSame(6279, (new DiasMora(6279))->dias,
            'el techo corta lo imposible, no lo improbable: 17 años de mora existen en cartera castigada');
    }

    public function test_el_limite_exacto_pasa(): void
    {
        $this->assertSame(DiasMora::MAXIMO_RAZONABLE, (new DiasMora(DiasMora::MAXIMO_RAZONABLE))->dias);
    }
}
