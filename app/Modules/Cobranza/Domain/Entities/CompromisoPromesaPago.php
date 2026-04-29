<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Domain\Entities;

use App\Modules\Cobranza\Domain\ValueObjects\FechaPromesa;
use App\Modules\Cobranza\Domain\ValueObjects\MontoPromesa;

/**
 * Especialización CTI del Compromiso para cobranza.
 * Un `Compromiso` base con `tipo_compromiso = 'promesa_pago'` tiene aquí sus datos específicos
 * (monto, moneda, tipo de pago). La transición de estado (cumplido/roto/cancelado) vive en `Compromiso`.
 */
final readonly class CompromisoPromesaPago
{
    private function __construct(
        public int $compromisoId,
        public int $proyectoId,
        public MontoPromesa $monto,
        public FechaPromesa $fechaVencimiento,
        public ?int $tipoPagoId,
    ) {
    }

    public static function registrar(
        int $compromisoId,
        int $proyectoId,
        MontoPromesa $monto,
        FechaPromesa $fechaVencimiento,
        ?int $tipoPagoId,
    ): self {
        return new self(
            compromisoId:     $compromisoId,
            proyectoId:       $proyectoId,
            monto:            $monto,
            fechaVencimiento: $fechaVencimiento,
            tipoPagoId:       $tipoPagoId,
        );
    }

    public static function reconstituir(
        int $compromisoId,
        int $proyectoId,
        MontoPromesa $monto,
        FechaPromesa $fechaVencimiento,
        ?int $tipoPagoId,
    ): self {
        return new self(
            compromisoId:     $compromisoId,
            proyectoId:       $proyectoId,
            monto:            $monto,
            fechaVencimiento: $fechaVencimiento,
            tipoPagoId:       $tipoPagoId,
        );
    }
}
