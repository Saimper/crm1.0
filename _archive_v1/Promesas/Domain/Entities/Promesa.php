<?php

declare(strict_types=1);

namespace App\Modules\Promesas\Domain\Entities;

use App\Modules\Promesas\Domain\Exceptions\TransicionPromesaInvalida;
use App\Modules\Promesas\Domain\ValueObjects\EstadoPromesa;
use App\Modules\Promesas\Domain\ValueObjects\FechaPromesa;
use App\Modules\Promesas\Domain\ValueObjects\MontoPromesa;
use DateTimeImmutable;

final readonly class Promesa
{
    private function __construct(
        public ?int $id,
        public string $publicId,
        public int $productoId,
        public int $gestionOrigenId,
        public int $usuarioId,
        public ?int $tipoPagoId,
        public MontoPromesa $monto,
        public FechaPromesa $fecha,
        public EstadoPromesa $estado,
        public ?DateTimeImmutable $fechaResolucion,
        public DateTimeImmutable $creadaEn,
    ) {}

    public static function crear(
        string $publicId,
        int $productoId,
        int $gestionOrigenId,
        int $usuarioId,
        ?int $tipoPagoId,
        MontoPromesa $monto,
        FechaPromesa $fecha,
        DateTimeImmutable $creadaEn,
    ): self {
        return new self(
            id: null,
            publicId: $publicId,
            productoId: $productoId,
            gestionOrigenId: $gestionOrigenId,
            usuarioId: $usuarioId,
            tipoPagoId: $tipoPagoId,
            monto: $monto,
            fecha: $fecha,
            estado: EstadoPromesa::PENDIENTE,
            fechaResolucion: null,
            creadaEn: $creadaEn,
        );
    }

    public static function reconstituir(
        int $id,
        string $publicId,
        int $productoId,
        int $gestionOrigenId,
        int $usuarioId,
        ?int $tipoPagoId,
        MontoPromesa $monto,
        FechaPromesa $fecha,
        EstadoPromesa $estado,
        ?DateTimeImmutable $fechaResolucion,
        DateTimeImmutable $creadaEn,
    ): self {
        return new self(
            id: $id,
            publicId: $publicId,
            productoId: $productoId,
            gestionOrigenId: $gestionOrigenId,
            usuarioId: $usuarioId,
            tipoPagoId: $tipoPagoId,
            monto: $monto,
            fecha: $fecha,
            estado: $estado,
            fechaResolucion: $fechaResolucion,
            creadaEn: $creadaEn,
        );
    }

    public function conId(int $id): self
    {
        return new self(
            id: $id,
            publicId: $this->publicId,
            productoId: $this->productoId,
            gestionOrigenId: $this->gestionOrigenId,
            usuarioId: $this->usuarioId,
            tipoPagoId: $this->tipoPagoId,
            monto: $this->monto,
            fecha: $this->fecha,
            estado: $this->estado,
            fechaResolucion: $this->fechaResolucion,
            creadaEn: $this->creadaEn,
        );
    }

    public function marcarCumplida(DateTimeImmutable $fechaResolucion): self
    {
        $this->asegurarPendiente();

        return $this->conEstadoResuelto(EstadoPromesa::CUMPLIDA, $fechaResolucion);
    }

    public function marcarRota(DateTimeImmutable $fechaResolucion): self
    {
        $this->asegurarPendiente();

        return $this->conEstadoResuelto(EstadoPromesa::ROTA, $fechaResolucion);
    }

    public function cancelar(DateTimeImmutable $fechaResolucion): self
    {
        $this->asegurarPendiente();

        return $this->conEstadoResuelto(EstadoPromesa::CANCELADA, $fechaResolucion);
    }

    private function asegurarPendiente(): void
    {
        if ($this->estado !== EstadoPromesa::PENDIENTE) {
            throw new TransicionPromesaInvalida(
                "No se puede cambiar el estado de una promesa en estado {$this->estado->value}."
            );
        }
    }

    private function conEstadoResuelto(EstadoPromesa $nuevoEstado, DateTimeImmutable $fechaResolucion): self
    {
        return new self(
            id: $this->id,
            publicId: $this->publicId,
            productoId: $this->productoId,
            gestionOrigenId: $this->gestionOrigenId,
            usuarioId: $this->usuarioId,
            tipoPagoId: $this->tipoPagoId,
            monto: $this->monto,
            fecha: $this->fecha,
            estado: $nuevoEstado,
            fechaResolucion: $fechaResolucion,
            creadaEn: $this->creadaEn,
        );
    }
}
