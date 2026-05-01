<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Importaciones;

use App\Modules\Importaciones\Domain\ValueObjects\ResumenChunk;
use PHPUnit\Framework\TestCase;

/**
 * F34C — coverage VO ResumenChunk (P2-8 ítem 2/7).
 */
final class ResumenChunkTest extends TestCase
{
    public function test_vacio_inicializa_contadores_en_cero(): void
    {
        $r = ResumenChunk::vacio();

        $this->assertSame(0, $r->procesadas);
        $this->assertSame(0, $r->validas);
        $this->assertSame(0, $r->invalidas);
        $this->assertSame(0, $r->omitidas);
        $this->assertSame(0, $r->duplicadas);
        $this->assertSame(0, $r->filasEnChunk);
        $this->assertNull($r->ultimaFilaId);
    }

    public function test_constructor_persiste_valores(): void
    {
        $r = new ResumenChunk(
            procesadas: 10,
            validas: 8,
            invalidas: 1,
            omitidas: 0,
            duplicadas: 1,
            filasEnChunk: 10,
            ultimaFilaId: 4242,
        );

        $this->assertSame(10, $r->procesadas);
        $this->assertSame(8, $r->validas);
        $this->assertSame(1, $r->invalidas);
        $this->assertSame(1, $r->duplicadas);
        $this->assertSame(4242, $r->ultimaFilaId);
    }
}
