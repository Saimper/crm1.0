<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\CamposPersonalizados\Domain;

use App\Modules\CamposPersonalizados\Domain\Exceptions\ReglaViolada;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\MarcadorTemporal;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class MarcadorTemporalTest extends TestCase
{
    protected function setUp(): void
    {
        CarbonImmutable::setTestNow('2026-04-30 12:34:56');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
    }

    public function test_token_hoy_resuelve_a_inicio_del_dia(): void
    {
        $m = MarcadorTemporal::desde('hoy');
        $this->assertSame('2026-04-30', $m->instante->format('Y-m-d'));
        $this->assertSame('00:00:00', $m->instante->format('H:i:s'));
    }

    public function test_token_ahora_incluye_hora_actual(): void
    {
        $m = MarcadorTemporal::desde('ahora');
        $this->assertSame('2026-04-30 12:34:56', $m->instante->format('Y-m-d H:i:s'));
    }

    public function test_offset_dias_positivo_y_negativo(): void
    {
        $this->assertSame('2026-05-07', MarcadorTemporal::desde('+7d')->instante->format('Y-m-d'));
        $this->assertSame('2026-04-29', MarcadorTemporal::desde('-1d')->instante->format('Y-m-d'));
    }

    public function test_iso_literal_aceptado(): void
    {
        $m = MarcadorTemporal::desde('2026-12-31');
        $this->assertSame('2026-12-31', $m->instante->format('Y-m-d'));
    }

    public function test_expresion_invalida_throws(): void
    {
        $this->expectException(ReglaViolada::class);
        MarcadorTemporal::desde('xyz123');
    }

    public function test_para_auto_fill_formatea_segun_tipo(): void
    {
        $m = MarcadorTemporal::desde('ahora');
        $this->assertSame('2026-04-30T12:34', $m->paraAutoFill(TipoCampo::FECHA_HORA));
        $this->assertSame('2026-04-30', $m->paraAutoFill(TipoCampo::FECHA));
    }
}
