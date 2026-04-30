<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Domain\Entities;

use App\Modules\Tenancy\Domain\ValueObjects\CodigoCartera;
use DateTimeImmutable;

final readonly class Cartera
{
    private function __construct(
        public ?int $id,
        public string $publicId,
        public int $proyectoId,
        public CodigoCartera $codigo,
        public string $nombre,
        public ?string $descripcion,
        public bool $activo,
        public DateTimeImmutable $creadaEn,
    ) {}

    public static function registrar(
        string $publicId,
        int $proyectoId,
        CodigoCartera $codigo,
        string $nombre,
        ?string $descripcion,
        DateTimeImmutable $creadaEn,
    ): self {
        return new self(
            id: null,
            publicId: $publicId,
            proyectoId: $proyectoId,
            codigo: $codigo,
            nombre: trim($nombre),
            descripcion: $descripcion !== null ? trim($descripcion) : null,
            activo: true,
            creadaEn: $creadaEn,
        );
    }

    public static function reconstituir(
        int $id,
        string $publicId,
        int $proyectoId,
        CodigoCartera $codigo,
        string $nombre,
        ?string $descripcion,
        bool $activo,
        DateTimeImmutable $creadaEn,
    ): self {
        return new self(
            id: $id,
            publicId: $publicId,
            proyectoId: $proyectoId,
            codigo: $codigo,
            nombre: $nombre,
            descripcion: $descripcion,
            activo: $activo,
            creadaEn: $creadaEn,
        );
    }

    public function conId(int $id): self
    {
        return new self(
            id: $id,
            publicId: $this->publicId,
            proyectoId: $this->proyectoId,
            codigo: $this->codigo,
            nombre: $this->nombre,
            descripcion: $this->descripcion,
            activo: $this->activo,
            creadaEn: $this->creadaEn,
        );
    }

    public function desactivar(): self
    {
        return new self(
            id: $this->id,
            publicId: $this->publicId,
            proyectoId: $this->proyectoId,
            codigo: $this->codigo,
            nombre: $this->nombre,
            descripcion: $this->descripcion,
            activo: false,
            creadaEn: $this->creadaEn,
        );
    }
}
