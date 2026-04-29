<?php

declare(strict_types=1);

namespace App\Modules\Casos\Domain\Entities;

use App\Modules\Casos\Domain\Exceptions\TransicionCasoInvalida;
use App\Modules\Casos\Domain\ValueObjects\TipoCaso;
use DateTimeImmutable;

final readonly class Caso
{
    private function __construct(
        public ?int $id,
        public string $publicId,
        public int $proyectoId,
        public int $carteraId,
        public int $personaId,
        public TipoCaso $tipoCaso,
        public int $estadoCasoId,
        public DateTimeImmutable $fechaIngreso,
        public int $prioridad,
        public ?DateTimeImmutable $cerradoEn,
        public DateTimeImmutable $creadaEn,
    ) {
    }

    public static function registrar(
        string $publicId,
        int $proyectoId,
        int $carteraId,
        int $personaId,
        TipoCaso $tipoCaso,
        int $estadoCasoId,
        DateTimeImmutable $fechaIngreso,
        int $prioridad,
        DateTimeImmutable $creadaEn,
    ): self {
        if ($prioridad < 0) {
            throw new TransicionCasoInvalida("La prioridad no puede ser negativa. Recibido: {$prioridad}.");
        }

        return new self(
            id: null,
            publicId: $publicId,
            proyectoId: $proyectoId,
            carteraId: $carteraId,
            personaId: $personaId,
            tipoCaso: $tipoCaso,
            estadoCasoId: $estadoCasoId,
            fechaIngreso: $fechaIngreso,
            prioridad: $prioridad,
            cerradoEn: null,
            creadaEn: $creadaEn,
        );
    }

    public static function reconstituir(
        int $id,
        string $publicId,
        int $proyectoId,
        int $carteraId,
        int $personaId,
        TipoCaso $tipoCaso,
        int $estadoCasoId,
        DateTimeImmutable $fechaIngreso,
        int $prioridad,
        ?DateTimeImmutable $cerradoEn,
        DateTimeImmutable $creadaEn,
    ): self {
        return new self(
            id: $id,
            publicId: $publicId,
            proyectoId: $proyectoId,
            carteraId: $carteraId,
            personaId: $personaId,
            tipoCaso: $tipoCaso,
            estadoCasoId: $estadoCasoId,
            fechaIngreso: $fechaIngreso,
            prioridad: $prioridad,
            cerradoEn: $cerradoEn,
            creadaEn: $creadaEn,
        );
    }

    public function conId(int $id): self
    {
        return new self(
            id: $id,
            publicId: $this->publicId,
            proyectoId: $this->proyectoId,
            carteraId: $this->carteraId,
            personaId: $this->personaId,
            tipoCaso: $this->tipoCaso,
            estadoCasoId: $this->estadoCasoId,
            fechaIngreso: $this->fechaIngreso,
            prioridad: $this->prioridad,
            cerradoEn: $this->cerradoEn,
            creadaEn: $this->creadaEn,
        );
    }

    public function cerrar(int $nuevoEstadoCasoId, DateTimeImmutable $cerradoEn): self
    {
        if ($this->cerradoEn !== null) {
            throw new TransicionCasoInvalida('El caso ya está cerrado.');
        }

        return new self(
            id: $this->id,
            publicId: $this->publicId,
            proyectoId: $this->proyectoId,
            carteraId: $this->carteraId,
            personaId: $this->personaId,
            tipoCaso: $this->tipoCaso,
            estadoCasoId: $nuevoEstadoCasoId,
            fechaIngreso: $this->fechaIngreso,
            prioridad: $this->prioridad,
            cerradoEn: $cerradoEn,
            creadaEn: $this->creadaEn,
        );
    }

    public function estaCerrado(): bool
    {
        return $this->cerradoEn !== null;
    }
}
