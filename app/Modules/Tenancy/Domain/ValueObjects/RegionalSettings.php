<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Domain\ValueObjects;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/** Regional presentation and explicit parsing; stored numbers remain canonical decimals. */
final readonly class RegionalSettings
{
    public function __construct(
        public string $timezone = 'UTC',
        public string $currency = 'USD',
        public string $dateFormat = 'd/m/Y',
        public int $decimalPlaces = 2,
        public string $decimalSeparator = '.',
        public string $thousandsSeparator = ',',
        public int $weekStartsOn = 1,
    ) {
        if (! in_array($timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            throw new InvalidArgumentException('Selecciona una zona horaria válida.');
        }
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('La moneda debe tener un código de tres letras mayúsculas.');
        }
        if (! in_array($dateFormat, ['d/m/Y', 'm/d/Y', 'Y-m-d'], true)) {
            throw new InvalidArgumentException('Selecciona un formato de fecha válido.');
        }
        if (! in_array($decimalPlaces, [2, 3], true)) {
            throw new InvalidArgumentException('La precisión debe ser de 2 o 3 decimales.');
        }
        if (! in_array($decimalSeparator, ['.', ','], true)
            || ! in_array($thousandsSeparator, ['.', ',', ' ', ''], true)
            || $decimalSeparator === $thousandsSeparator) {
            throw new InvalidArgumentException('Los separadores decimal y de miles deben ser distintos.');
        }
        if ($weekStartsOn < 1 || $weekStartsOn > 7) {
            throw new InvalidArgumentException('Selecciona un inicio de semana válido.');
        }
    }

    /** @param array<string, mixed> $values */
    public static function fromArray(array $values): self
    {
        foreach (['zona_horaria', 'moneda', 'formato_fecha', 'decimales', 'separador_decimal', 'separador_miles', 'inicio_semana'] as $field) {
            if (isset($values[$field]) && ! is_scalar($values[$field])) {
                throw new InvalidArgumentException('La configuración regional contiene un valor inválido.');
            }
        }

        return new self(
            timezone: (string) ($values['zona_horaria'] ?? 'UTC'),
            currency: (string) ($values['moneda'] ?? 'USD'),
            dateFormat: (string) ($values['formato_fecha'] ?? 'd/m/Y'),
            decimalPlaces: (int) ($values['decimales'] ?? 2),
            decimalSeparator: (string) ($values['separador_decimal'] ?? '.'),
            thousandsSeparator: (string) ($values['separador_miles'] ?? ','),
            weekStartsOn: (int) ($values['inicio_semana'] ?? 1),
        );
    }

    /** @return array<string, string|int> */
    public function toArray(): array
    {
        return ['zona_horaria' => $this->timezone, 'moneda' => $this->currency,
            'formato_fecha' => $this->dateFormat, 'decimales' => $this->decimalPlaces,
            'separador_decimal' => $this->decimalSeparator, 'separador_miles' => $this->thousandsSeparator,
            'inicio_semana' => $this->weekStartsOn];
    }

    /** Reject lost precision; trailing zeroes from DECIMAL storage are harmless. */
    public function validateCanonical(string $value): string
    {
        if (! is_numeric($value) || ! preg_match('/^-?\d+(?:\.\d+)?$/D', $value)) {
            throw new InvalidArgumentException('El monto debe ser un número decimal válido.');
        }
        $fraction = explode('.', $value, 2)[1] ?? '';
        if (strlen(rtrim($fraction, '0')) > $this->decimalPlaces) {
            throw new InvalidArgumentException("El proyecto admite como máximo {$this->decimalPlaces} decimales; no se redondeó el monto.");
        }

        return strlen($fraction) > 3 ? bcadd($value, '0', 3) : $value;
    }

    /** Strict source-format parsing: ambiguity is resolved by the selected regional settings. */
    public function parseNumber(string $raw): string
    {
        $raw = str_replace(["\u{00A0}", "\u{202F}"], ' ', trim($raw));
        $negative = str_starts_with($raw, '(') && str_ends_with($raw, ')');
        if ($negative) {
            $raw = substr($raw, 1, -1);
        }
        $raw = trim((string) preg_replace('/^(?:[A-Z]{3}|[$€£])\s*|\s*(?:[A-Z]{3}|[$€£])$/u', '', $raw));
        $decimal = preg_quote($this->decimalSeparator, '/');
        $group = preg_quote($this->thousandsSeparator, '/');
        $integer = $group === '' ? '\d+' : '(?:\d+|\d{1,3}(?:'.$group.'\d{3})+)';
        if (! preg_match('/^-?'.$integer.'(?:'.$decimal.'\d+)?$/D', $raw)) {
            throw new InvalidArgumentException('El número no coincide con los separadores configurados.');
        }
        $canonical = $this->thousandsSeparator === '' ? $raw : str_replace($this->thousandsSeparator, '', $raw);
        $canonical = str_replace($this->decimalSeparator, '.', $canonical);
        if ($negative) {
            $canonical = '-'.ltrim($canonical, '-');
        }

        return $this->validateCanonical($canonical);
    }

    /** Calendar dates are never shifted; timestamps entered locally are returned as UTC. */
    public function parseDate(string $raw, bool $withTime = false): DateTimeImmutable
    {
        $raw = trim($raw);
        $dateFormat = preg_match('/^\d{4}-\d{2}-\d{2}(?:$|[ T])/', $raw) ? 'Y-m-d' : $this->dateFormat;
        $raw = str_replace('T', ' ', $raw);
        $formats = $withTime ? [$dateFormat.' H:i:s', $dateFormat.' H:i'] : [$dateFormat];
        foreach ($formats as $format) {
            $value = DateTimeImmutable::createFromFormat('!'.$format, $raw, new DateTimeZone($this->timezone));
            $errors = DateTimeImmutable::getLastErrors();
            if ($value !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) && $value->format($format) === $raw) {
                return $withTime ? $value->setTimezone(new DateTimeZone('UTC')) : $value;
            }
        }
        throw new InvalidArgumentException('La fecha no coincide con el formato configurado o no existe.');
    }

    /** Decimal string formatting avoids binary-float loss for large financial values. */
    public function formatNumber(string|int|float $value, ?int $decimals = null, bool $group = true): string
    {
        $decimals ??= $this->decimalPlaces;
        $decimals = max(0, min(6, $decimals));
        $value = (string) $value;
        $negative = str_starts_with($value, '-');
        $absolute = $negative ? substr($value, 1) : $value;
        if (! is_numeric($absolute) || ! preg_match('/^\d+(?:\.\d+)?$/D', $absolute)) {
            return '—';
        }
        $rounding = ['0.5', '0.05', '0.005', '0.0005', '0.00005', '0.000005', '0.0000005'][$decimals];
        $rounded = bcadd($absolute, $rounding, $decimals);
        [$integer, $fraction] = array_pad(explode('.', $rounded, 2), 2, '');
        if ($group && $this->thousandsSeparator !== '') {
            $integer = (string) preg_replace('/\B(?=(\d{3})+(?!\d))/', $this->thousandsSeparator, $integer);
        }

        return ($negative && bccomp($rounded, '0', $decimals) !== 0 ? '-' : '').$integer
            .($decimals > 0 ? $this->decimalSeparator.str_pad($fraction, $decimals, '0') : '');
    }

    public function formatDate(mixed $value, bool $withTime = false): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        $date = $value instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($value) : new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));
        if ($withTime) {
            $date = $date->setTimezone(new DateTimeZone($this->timezone));
        }

        return $date->format($this->dateFormat.($withTime ? ' H:i' : ''));
    }
}
