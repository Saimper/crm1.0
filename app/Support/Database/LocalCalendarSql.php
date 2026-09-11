<?php

declare(strict_types=1);

namespace App\Support\Database;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** MySQL calendar grouping without requiring installed timezone tables. */
final class LocalCalendarSql
{
    public static function expression(string $column, string $timezone, string $format = '%Y-%m-%d'): string
    {
        if (! preg_match('/^[a-z_]+(?:\.[a-z_]+)?$/D', $column) || ! in_array($format, ['%Y-%m-%d', '%Y-%m'], true)) {
            throw new InvalidArgumentException('Expresión de fecha no permitida.');
        }
        if (! in_array($timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            throw new InvalidArgumentException('Zona horaria no admitida.');
        }
        $zone = new DateTimeZone($timezone);
        $utc = new DateTimeZone('UTC');
        $from = (new DateTimeImmutable('1900-01-01', $utc))->getTimestamp();
        $to = (new DateTimeImmutable('2101-01-01', $utc))->getTimestamp();
        $transitions = $zone->getTransitions($from, $to);
        if ($transitions === []) {
            throw new InvalidArgumentException('Zona horaria no admitida.');
        }
        $offset = (int) $transitions[0]['offset'];
        $branches = [];
        foreach (array_slice($transitions, 1) as $transition) {
            $nextOffset = (int) $transition['offset'];
            if ($nextOffset === $offset) {
                continue;
            }
            $boundary = (new DateTimeImmutable('@'.$transition['ts']))->setTimezone($utc)->format('Y-m-d H:i:s');
            $branches[] = "WHEN {$column} < '{$boundary}' THEN {$offset}";
            $offset = $nextOffset;
        }
        $shift = $branches === [] ? (string) $offset : '(CASE '.implode(' ', $branches).' ELSE '.$offset.' END)';

        return "DATE_FORMAT(DATE_ADD({$column}, INTERVAL {$shift} SECOND), '{$format}')";
    }
}
