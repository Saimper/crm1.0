<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Domain\Entities;

use App\Modules\Tenancy\Domain\ValueObjects\CodigoMandante;
use DateTimeImmutable;

final readonly class Mandante
{
    private function __construct(
        public ?int $id,
        public string $publicId,
        public CodigoMandante $codigo,
        public string $nombre,
        public ?string $documento,
        public bool $activo,
        public DateTimeImmutable $creadaEn,
    ) {}

    public static function registrar(
        string $publicId,
        CodigoMandante $codigo,
        string $nombre,
        ?string $documento,
        DateTimeImmutable $creadaEn,
    ): self {
        return new self(
            id: null,
            publicId: $publicId,
            codigo: $codigo,
            nombre: trim($nombre),
            documento: $documento !== null ? trim($documento) : null,
            activo: true,
            creadaEn: $creadaEn,
        );
    }

    public static function reconstituir(
        int $id,
        string $publicId,
        CodigoMandante $codigo,
        string $nombre,
        ?string $documento,
        bool $activo,
        DateTimeImmutable $creadaEn,
    ): self {
        return new self(
            id: $id,
            publicId: $publicId,
            codigo: $codigo,
            nombre: $nombre,
            documento: $documento,
            activo: $activo,
            creadaEn: $creadaEn,
        );
    }

    public function conId(int $id): self
    {
        return new self(
            id: $id,
            publicId: $this->publicId,
            codigo: $this->codigo,
            nombre: $this->nombre,
            documento: $this->documento,
            activo: $this->activo,
            creadaEn: $this->creadaEn,
        );
    }

    public function desactivar(): self
    {
        return new self(
            id: $this->id,
            publicId: $this->publicId,
            codigo: $this->codigo,
            nombre: $this->nombre,
            documento: $this->documento,
            activo: false,
            creadaEn: $this->creadaEn,
        );
    }
}
