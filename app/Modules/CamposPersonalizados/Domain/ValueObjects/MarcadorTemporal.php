<?php

declare(strict_types=1);

namespace App\Modules\CamposPersonalizados\Domain\ValueObjects;

use App\Modules\CamposPersonalizados\Domain\Exceptions\ReglaViolada;
use Carbon\CarbonImmutable;

/**
 * Marcador temporal declarativo para reglas `fecha_minima` y `fecha_maxima` (§7.4 CLAUDE.md).
 * Resuelve tokens dinámicos a un instante concreto sin lógica ejecutable por usuario final.
 *
 * Tokens soportados:
 *   "hoy"   → CarbonImmutable::today() (00:00 del día actual)
 *   "ahora" → CarbonImmutable::now()
 *   "+Nd"   → today() + N días (N entero, soporta negativos: "-3d")
 *   ISO     → fecha o fecha-hora literal interpretable por Carbon
 */
final readonly class MarcadorTemporal
{
    private function __construct(public CarbonImmutable $instante) {}

    public static function desde(string $expresion): self
    {
        $expresion = trim($expresion);
        if ($expresion === '') {
            throw new ReglaViolada('Marcador temporal vacío.');
        }

        if ($expresion === 'hoy') {
            return new self(CarbonImmutable::today());
        }
        if ($expresion === 'ahora') {
            return new self(CarbonImmutable::now());
        }

        if (preg_match('/^([+-])(\d+)d$/', $expresion, $m) === 1) {
            $signo = $m[1] === '-' ? -1 : 1;
            $dias = $signo * (int) $m[2];

            return new self(CarbonImmutable::today()->addDays($dias));
        }

        $ts = @strtotime($expresion);
        if ($ts === false) {
            throw new ReglaViolada("Marcador temporal inválido: «{$expresion}».");
        }
        $carbon = CarbonImmutable::createFromTimestamp($ts);

        return new self($carbon);
    }

    public function paraComparar(TipoCampo $tipo): int
    {
        return $tipo === TipoCampo::FECHA_HORA
            ? $this->instante->getTimestamp()
            : $this->instante->startOfDay()->getTimestamp();
    }

    public function paraAutoFill(TipoCampo $tipo): string
    {
        return match ($tipo) {
            TipoCampo::FECHA => $this->instante->format('Y-m-d'),
            TipoCampo::FECHA_HORA => $this->instante->format('Y-m-d\TH:i'),
            default => $this->instante->toIso8601String(),
        };
    }
}
