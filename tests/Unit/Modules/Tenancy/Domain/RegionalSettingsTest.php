<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Tenancy\Domain;

use App\Modules\Cobranza\Domain\ValueObjects\MontoCobranza;
use App\Modules\Cobranza\Domain\ValueObjects\MontoPromesa;
use App\Modules\Tenancy\Domain\ValueObjects\RegionalSettings;
use App\Modules\Venta\Domain\ValueObjects\MontoCierre;
use App\Modules\Venta\Domain\ValueObjects\ValorEstimadoVenta;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RegionalSettingsTest extends TestCase
{
    public function test_formats_and_parses_real_three_decimal_amounts_without_float_loss(): void
    {
        $settings = new RegionalSettings(decimalPlaces: 3, decimalSeparator: ',', thousandsSeparator: '.');
        $this->assertSame('1234567890123.456', $settings->parseNumber('1.234.567.890.123,456'));
        $this->assertSame('1.234.567.890.123,456', $settings->formatNumber('1234567890123.456'));
        $this->assertSame('-1.234,567', $settings->formatNumber('-1234.567'));
        $this->assertSame('-1234.567', $settings->parseNumber('(1.234,567)'));
        $this->assertSame('0,001', $settings->formatNumber('0.001'));
    }

    public function test_source_settings_disambiguate_a_single_separator(): void
    {
        $this->assertSame('1234', (new RegionalSettings)->parseNumber('1,234'));
        $this->assertSame('1.234', (new RegionalSettings(decimalPlaces: 3, decimalSeparator: ',', thousandsSeparator: '.'))->parseNumber('1,234'));
    }

    public function test_two_decimal_precision_rejects_a_meaningful_third_digit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new RegionalSettings)->validateCanonical('12.345');
    }

    public function test_zero_padding_in_the_database_does_not_change_precision(): void
    {
        $this->assertSame('12.340', (new RegionalSettings)->validateCanonical('12.340'));
        $this->assertSame('1,234.57', (new RegionalSettings)->formatNumber('1234.567'));
    }

    public function test_rejects_incorrect_grouping_instead_of_guessing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new RegionalSettings)->parseNumber('12,34.56');
    }

    public function test_requires_distinct_separators(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RegionalSettings(decimalSeparator: ',', thousandsSeparator: ',');
    }

    public function test_configured_dates_and_iso_calendar_inputs_are_unambiguous(): void
    {
        $settings = new RegionalSettings(timezone: 'America/Panama');
        $this->assertSame('2026-09-10', $settings->parseDate('10/09/2026')->format('Y-m-d'));
        $this->assertSame('2026-09-10', $settings->parseDate('2026-09-10')->format('Y-m-d'));
        $this->assertSame('2026-09-11 02:30:00', $settings->parseDate('2026-09-10T21:30', true)->format('Y-m-d H:i:s'));
        $this->assertSame('10/09/2026', $settings->formatDate('2026-09-10'));
        $this->assertSame('09/09/2026 21:30', $settings->formatDate('2026-09-10 02:30:00', true));
    }

    public function test_invalid_calendar_dates_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new RegionalSettings)->parseDate('31/02/2026');
    }

    public function test_money_domain_preserves_thousandths_and_positive_small_values(): void
    {
        $this->assertFalse((new MontoCobranza('0.001'))->esCero());
        $this->assertSame('0.001', (new MontoPromesa('0.001'))->monto);
        $this->assertSame('0.001', (new MontoCierre('0.001'))->monto);
        $this->assertSame('0.001', (new ValorEstimadoVenta('0.001'))->monto);
    }
}
