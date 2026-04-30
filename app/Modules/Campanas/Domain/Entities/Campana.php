<?php

declare(strict_types=1);

namespace App\Modules\Campanas\Domain\Entities;

use App\Modules\Campanas\Domain\Exceptions\RangoFechasCampanaInvalido;
use App\Modules\Campanas\Domain\ValueObjects\CodigoCampana;
use App\Modules\Campanas\Domain\ValueObjects\EstadoCampana;
use DateTimeImmutable;

final readonly class Campana
{
    private function __construct(
        public ?int $id,
        public string $publicId,
        public int $proyectoId,
        public CodigoCampana $codigo,
        public string $nombre,
        public ?string $descripcion,
        public EstadoCampana $estado,
        public DateTimeImmutable $fechaInicio,
        public ?DateTimeImmutable $fechaFin,
        public ?int $creadaPorId,
        public DateTimeImmutable $creadaEn,
    ) {}

    public static function registrar(
        string $publicId,
        int $proyectoId,
        CodigoCampana $codigo,
        string $nombre,
        ?string $descripcion,
        DateTimeImmutable $fechaInicio,
        ?DateTimeImmutable $fechaFin,
        ?int $creadaPorId,
        DateTimeImmutable $creadaEn,
    ): self {
        if ($fechaFin !== null && $fechaFin < $fechaInicio) {
            throw new RangoFechasCampanaInvalido('La fecha fin de la campaña no puede ser anterior a la fecha de inicio.');
        }

        return new self(
            id: null,
            publicId: $publicId,
            proyectoId: $proyectoId,
            codigo: $codigo,
            nombre: trim($nombre),
            descripcion: $descripcion !== null ? trim($descripcion) : null,
            estado: EstadoCampana::PROGRAMADA,
            fechaInicio: $fechaInicio,
            fechaFin: $fechaFin,
            creadaPorId: $creadaPorId,
            creadaEn: $creadaEn,
        );
    }

    public static function reconstituir(
        int $id,
        string $publicId,
        int $proyectoId,
        CodigoCampana $codigo,
        string $nombre,
        ?string $descripcion,
        EstadoCampana $estado,
        DateTimeImmutable $fechaInicio,
        ?DateTimeImmutable $fechaFin,
        ?int $creadaPorId,
        DateTimeImmutable $creadaEn,
    ): self {
        return new self($id, $publicId, $proyectoId, $codigo, $nombre, $descripcion, $estado, $fechaInicio, $fechaFin, $creadaPorId, $creadaEn);
    }

    public function conId(int $id): self
    {
        return new self($id, $this->publicId, $this->proyectoId, $this->codigo, $this->nombre, $this->descripcion, $this->estado, $this->fechaInicio, $this->fechaFin, $this->creadaPorId, $this->creadaEn);
    }
}
