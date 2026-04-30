<?php

declare(strict_types=1);

namespace App\Modules\Contactos\Application\DTOs;

use App\Modules\Contactos\Domain\ValueObjects\TipoContacto;
use DateTimeImmutable;

final readonly class RegistrarContactoInput
{
    public function __construct(
        public int $proyectoId,
        public int $personaId,
        public TipoContacto $tipo,
        public string $valor,
        public ?string $etiqueta,
        public bool $esPrincipal,
        public DateTimeImmutable $creadaEn,
    ) {}
}
