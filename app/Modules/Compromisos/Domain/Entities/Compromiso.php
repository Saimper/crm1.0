<?php

declare(strict_types=1);

namespace App\Modules\Compromisos\Domain\Entities;

use App\Modules\Compromisos\Domain\Exceptions\TransicionCompromisoInvalida;
use App\Modules\Compromisos\Domain\ValueObjects\EstadoCompromiso;
use App\Modules\Compromisos\Domain\ValueObjects\TipoCompromiso;
use DateTimeImmutable;

final readonly class Compromiso
{
    private function __construct(
        public ?int $id,
        public string $publicId,
        public int $proyectoId,
        public int $casoId,
        public ?int $gestionOrigenId,
        public int $usuarioId,
        public TipoCompromiso $tipo,
        public EstadoCompromiso $estado,
        public DateTimeImmutable $fechaVencimiento,
        public ?DateTimeImmutable $fechaResolucion,
        public DateTimeImmutable $creadaEn,
    ) {}

    public static function crear(
        string $publicId,
        int $proyectoId,
        int $casoId,
        ?int $gestionOrigenId,
        int $usuarioId,
        TipoCompromiso $tipo,
        DateTimeImmutable $fechaVencimiento,
        DateTimeImmutable $creadaEn,
    ): self {
        return new self(
            id: null,
            publicId: $publicId,
            proyectoId: $proyectoId,
            casoId: $casoId,
            gestionOrigenId: $gestionOrigenId,
            usuarioId: $usuarioId,
            tipo: $tipo,
            estado: EstadoCompromiso::PENDIENTE,
            fechaVencimiento: $fechaVencimiento,
            fechaResolucion: null,
            creadaEn: $creadaEn,
        );
    }

    public static function reconstituir(
        int $id,
        string $publicId,
        int $proyectoId,
        int $casoId,
        ?int $gestionOrigenId,
        int $usuarioId,
        TipoCompromiso $tipo,
        EstadoCompromiso $estado,
        DateTimeImmutable $fechaVencimiento,
        ?DateTimeImmutable $fechaResolucion,
        DateTimeImmutable $creadaEn,
    ): self {
        return new self(
            id: $id,
            publicId: $publicId,
            proyectoId: $proyectoId,
            casoId: $casoId,
            gestionOrigenId: $gestionOrigenId,
            usuarioId: $usuarioId,
            tipo: $tipo,
            estado: $estado,
            fechaVencimiento: $fechaVencimiento,
            fechaResolucion: $fechaResolucion,
            creadaEn: $creadaEn,
        );
    }

    public function conId(int $id): self
    {
        return new self(
            id: $id,
            publicId: $this->publicId,
            proyectoId: $this->proyectoId,
            casoId: $this->casoId,
            gestionOrigenId: $this->gestionOrigenId,
            usuarioId: $this->usuarioId,
            tipo: $this->tipo,
            estado: $this->estado,
            fechaVencimiento: $this->fechaVencimiento,
            fechaResolucion: $this->fechaResolucion,
            creadaEn: $this->creadaEn,
        );
    }

    public function marcarCumplido(DateTimeImmutable $fechaResolucion): self
    {
        $this->asegurarPendiente();

        return $this->conEstado(EstadoCompromiso::CUMPLIDO, $fechaResolucion);
    }

    public function marcarRoto(DateTimeImmutable $fechaResolucion): self
    {
        $this->asegurarPendiente();

        return $this->conEstado(EstadoCompromiso::ROTO, $fechaResolucion);
    }

    public function cancelar(DateTimeImmutable $fechaResolucion): self
    {
        $this->asegurarPendiente();

        return $this->conEstado(EstadoCompromiso::CANCELADO, $fechaResolucion);
    }

    public function estaResuelto(): bool
    {
        return $this->estado !== EstadoCompromiso::PENDIENTE;
    }

    private function asegurarPendiente(): void
    {
        if ($this->estado !== EstadoCompromiso::PENDIENTE) {
            throw new TransicionCompromisoInvalida(
                "No se puede cambiar el estado de un compromiso en estado {$this->estado->value}."
            );
        }
    }

    private function conEstado(EstadoCompromiso $nuevoEstado, DateTimeImmutable $fechaResolucion): self
    {
        return new self(
            id: $this->id,
            publicId: $this->publicId,
            proyectoId: $this->proyectoId,
            casoId: $this->casoId,
            gestionOrigenId: $this->gestionOrigenId,
            usuarioId: $this->usuarioId,
            tipo: $this->tipo,
            estado: $nuevoEstado,
            fechaVencimiento: $this->fechaVencimiento,
            fechaResolucion: $fechaResolucion,
            creadaEn: $this->creadaEn,
        );
    }
}
