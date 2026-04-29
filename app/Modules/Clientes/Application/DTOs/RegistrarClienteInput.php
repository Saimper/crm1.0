<?php

declare(strict_types=1);

namespace App\Modules\Clientes\Application\DTOs;

use App\Modules\Clientes\Domain\ValueObjects\Identificacion;
use App\Modules\Clientes\Domain\ValueObjects\TipoPersona;
use DateTimeImmutable;

final readonly class RegistrarClienteInput
{
    public function __construct(
        public string $publicId,
        public TipoPersona $tipoPersona,
        public int $tipoIdentificacionId,
        public Identificacion $identificacion,
        public ?string $nombres,
        public ?string $apellidos,
        public ?string $razonSocial,
        public ?DateTimeImmutable $fechaNacimiento,
        public DateTimeImmutable $creadaEn,
    ) {
    }
}
