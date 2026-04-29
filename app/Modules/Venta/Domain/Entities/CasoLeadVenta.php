<?php

declare(strict_types=1);

namespace App\Modules\Venta\Domain\Entities;

use App\Modules\Venta\Domain\Exceptions\DatosLeadInvalidos;
use App\Modules\Venta\Domain\ValueObjects\CodigoLead;
use App\Modules\Venta\Domain\ValueObjects\ValorEstimadoVenta;
use DateTimeImmutable;

/**
 * Especialización CTI 1:1 del núcleo Caso para operaciones de venta (lead/oportunidad).
 */
final readonly class CasoLeadVenta
{
    private function __construct(
        public int $casoId,
        public int $proyectoId,
        public CodigoLead $codigoLead,
        public ?int $productoVentaId,
        public ?int $etapaEmbudoId,
        public ValorEstimadoVenta $valorEstimado,
        public ?string $origenLead,
        public DateTimeImmutable $fechaPrimerContacto,
        public ?DateTimeImmutable $fechaEstimadaCierre,
    ) {
    }

    public static function registrar(
        int $casoId,
        int $proyectoId,
        CodigoLead $codigoLead,
        ?int $productoVentaId,
        ?int $etapaEmbudoId,
        ValorEstimadoVenta $valorEstimado,
        ?string $origenLead,
        DateTimeImmutable $fechaPrimerContacto,
        ?DateTimeImmutable $fechaEstimadaCierre,
    ): self {
        if ($fechaEstimadaCierre !== null && $fechaEstimadaCierre < $fechaPrimerContacto) {
            throw new DatosLeadInvalidos('La fecha estimada de cierre no puede ser anterior al primer contacto.');
        }

        return new self(
            casoId:              $casoId,
            proyectoId:          $proyectoId,
            codigoLead:          $codigoLead,
            productoVentaId:     $productoVentaId,
            etapaEmbudoId:       $etapaEmbudoId,
            valorEstimado:       $valorEstimado,
            origenLead:          $origenLead,
            fechaPrimerContacto: $fechaPrimerContacto,
            fechaEstimadaCierre: $fechaEstimadaCierre,
        );
    }

    public static function reconstituir(
        int $casoId,
        int $proyectoId,
        CodigoLead $codigoLead,
        ?int $productoVentaId,
        ?int $etapaEmbudoId,
        ValorEstimadoVenta $valorEstimado,
        ?string $origenLead,
        DateTimeImmutable $fechaPrimerContacto,
        ?DateTimeImmutable $fechaEstimadaCierre,
    ): self {
        return new self(
            casoId:              $casoId,
            proyectoId:          $proyectoId,
            codigoLead:          $codigoLead,
            productoVentaId:     $productoVentaId,
            etapaEmbudoId:       $etapaEmbudoId,
            valorEstimado:       $valorEstimado,
            origenLead:          $origenLead,
            fechaPrimerContacto: $fechaPrimerContacto,
            fechaEstimadaCierre: $fechaEstimadaCierre,
        );
    }
}
