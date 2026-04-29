<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application\DTOs;

use App\Modules\Tenancy\Domain\ValueObjects\CodigoMandante;
use DateTimeImmutable;

final readonly class RegistrarMandanteInput
{
    public function __construct(
        public string $publicId,
        public CodigoMandante $codigo,
        public string $nombre,
        public ?string $documento,
        public DateTimeImmutable $creadaEn,
    ) {
    }
}
