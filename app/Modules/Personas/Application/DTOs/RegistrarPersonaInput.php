<?php

declare(strict_types=1);

namespace App\Modules\Personas\Application\DTOs;

use App\Modules\Personas\Domain\ValueObjects\Identificacion;
use App\Modules\Personas\Domain\ValueObjects\TipoPersona;
use DateTimeImmutable;

final readonly class RegistrarPersonaInput
{
    public function __construct(
        public string $publicId,
        public int $proyectoId,
        public TipoPersona $tipoPersona,
        public int $tipoIdentificacionId,
        public Identificacion $identificacion,
        public ?string $nombres,
        public ?string $apellidos,
        public ?string $razonSocial,
        public ?DateTimeImmutable $fechaNacimiento,
        public DateTimeImmutable $creadaEn,
    ) {}
}
