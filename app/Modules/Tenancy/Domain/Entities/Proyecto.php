<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Domain\Entities;

use App\Modules\Tenancy\Domain\Exceptions\RangoFechasProyectoInvalido;
use App\Modules\Tenancy\Domain\ValueObjects\CodigoProyecto;
use App\Modules\Tenancy\Domain\ValueObjects\TipoOperacion;
use DateTimeImmutable;

final readonly class Proyecto
{
    private function __construct(
        public ?int $id,
        public string $publicId,
        public int $mandanteId,
        public CodigoProyecto $codigo,
        public string $nombre,
        public ?string $descripcion,
        public TipoOperacion $tipoOperacion,
        public bool $activo,
        public ?DateTimeImmutable $fechaInicio,
        public ?DateTimeImmutable $fechaFin,
        public DateTimeImmutable $creadaEn,
    ) {}

    public static function registrar(
        string $publicId,
        int $mandanteId,
        CodigoProyecto $codigo,
        string $nombre,
        ?string $descripcion,
        TipoOperacion $tipoOperacion,
        ?DateTimeImmutable $fechaInicio,
        ?DateTimeImmutable $fechaFin,
        DateTimeImmutable $creadaEn,
    ): self {
        if ($fechaInicio !== null && $fechaFin !== null && $fechaFin < $fechaInicio) {
            throw new RangoFechasProyectoInvalido(
                'La fecha de fin del proyecto no puede ser anterior a la fecha de inicio.'
            );
        }

        return new self(
            id: null,
            publicId: $publicId,
            mandanteId: $mandanteId,
            codigo: $codigo,
            nombre: trim($nombre),
            descripcion: $descripcion !== null ? trim($descripcion) : null,
            tipoOperacion: $tipoOperacion,
            activo: true,
            fechaInicio: $fechaInicio,
            fechaFin: $fechaFin,
            creadaEn: $creadaEn,
        );
    }

    public static function reconstituir(
        int $id,
        string $publicId,
        int $mandanteId,
        CodigoProyecto $codigo,
        string $nombre,
        ?string $descripcion,
        TipoOperacion $tipoOperacion,
        bool $activo,
        ?DateTimeImmutable $fechaInicio,
        ?DateTimeImmutable $fechaFin,
        DateTimeImmutable $creadaEn,
    ): self {
        return new self(
            id: $id,
            publicId: $publicId,
            mandanteId: $mandanteId,
            codigo: $codigo,
            nombre: $nombre,
            descripcion: $descripcion,
            tipoOperacion: $tipoOperacion,
            activo: $activo,
            fechaInicio: $fechaInicio,
            fechaFin: $fechaFin,
            creadaEn: $creadaEn,
        );
    }

    public function conId(int $id): self
    {
        return new self(
            id: $id,
            publicId: $this->publicId,
            mandanteId: $this->mandanteId,
            codigo: $this->codigo,
            nombre: $this->nombre,
            descripcion: $this->descripcion,
            tipoOperacion: $this->tipoOperacion,
            activo: $this->activo,
            fechaInicio: $this->fechaInicio,
            fechaFin: $this->fechaFin,
            creadaEn: $this->creadaEn,
        );
    }

    public function desactivar(): self
    {
        return new self(
            id: $this->id,
            publicId: $this->publicId,
            mandanteId: $this->mandanteId,
            codigo: $this->codigo,
            nombre: $this->nombre,
            descripcion: $this->descripcion,
            tipoOperacion: $this->tipoOperacion,
            activo: false,
            fechaInicio: $this->fechaInicio,
            fechaFin: $this->fechaFin,
            creadaEn: $this->creadaEn,
        );
    }
}
