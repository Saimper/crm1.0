<?php

declare(strict_types=1);

namespace App\Modules\Asignaciones\Domain\Entities;

use App\Modules\Asignaciones\Domain\Exceptions\TransicionAsignacionInvalida;
use App\Modules\Asignaciones\Domain\ValueObjects\EstadoAsignacion;
use DateTimeImmutable;

final readonly class Asignacion
{
    private function __construct(
        public ?int $id,
        public string $publicId,
        public int $proyectoId,
        public int $campanaId,
        public int $casoId,
        public int $usuarioId,
        public DateTimeImmutable $fechaAsignacion,
        public int $prioridad,
        public EstadoAsignacion $estado,
        public ?DateTimeImmutable $cerradaEn,
        public DateTimeImmutable $creadaEn,
    ) {
    }

    public static function registrar(
        string $publicId,
        int $proyectoId,
        int $campanaId,
        int $casoId,
        int $usuarioId,
        DateTimeImmutable $fechaAsignacion,
        int $prioridad,
        DateTimeImmutable $creadaEn,
    ): self {
        if ($prioridad < 0) {
            throw new TransicionAsignacionInvalida("Prioridad no puede ser negativa. Recibido: {$prioridad}.");
        }

        return new self(
            id: null,
            publicId: $publicId,
            proyectoId: $proyectoId,
            campanaId: $campanaId,
            casoId: $casoId,
            usuarioId: $usuarioId,
            fechaAsignacion: $fechaAsignacion,
            prioridad: $prioridad,
            estado: EstadoAsignacion::PENDIENTE,
            cerradaEn: null,
            creadaEn: $creadaEn,
        );
    }

    public static function reconstituir(
        int $id,
        string $publicId,
        int $proyectoId,
        int $campanaId,
        int $casoId,
        int $usuarioId,
        DateTimeImmutable $fechaAsignacion,
        int $prioridad,
        EstadoAsignacion $estado,
        ?DateTimeImmutable $cerradaEn,
        DateTimeImmutable $creadaEn,
    ): self {
        return new self($id, $publicId, $proyectoId, $campanaId, $casoId, $usuarioId, $fechaAsignacion, $prioridad, $estado, $cerradaEn, $creadaEn);
    }

    public function conId(int $id): self
    {
        return new self($id, $this->publicId, $this->proyectoId, $this->campanaId, $this->casoId, $this->usuarioId, $this->fechaAsignacion, $this->prioridad, $this->estado, $this->cerradaEn, $this->creadaEn);
    }

    public function cerrar(DateTimeImmutable $cerradaEn): self
    {
        if ($this->estado === EstadoAsignacion::CERRADA) {
            throw new TransicionAsignacionInvalida('La asignación ya está cerrada.');
        }

        return new self(
            id: $this->id,
            publicId: $this->publicId,
            proyectoId: $this->proyectoId,
            campanaId: $this->campanaId,
            casoId: $this->casoId,
            usuarioId: $this->usuarioId,
            fechaAsignacion: $this->fechaAsignacion,
            prioridad: $this->prioridad,
            estado: EstadoAsignacion::CERRADA,
            cerradaEn: $cerradaEn,
            creadaEn: $this->creadaEn,
        );
    }
}
