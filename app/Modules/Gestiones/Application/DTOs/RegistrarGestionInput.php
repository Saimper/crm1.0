<?php

declare(strict_types=1);

namespace App\Modules\Gestiones\Application\DTOs;

use App\Modules\Gestiones\Domain\ValueObjects\DatosCompromiso;
use App\Modules\Gestiones\Domain\ValueObjects\DuracionSegundos;
use DateTimeImmutable;

final readonly class RegistrarGestionInput
{
    public function __construct(
        public string $publicId,
        public int $proyectoId,
        public int $casoId,
        public int $personaId,
        public ?int $contactoId,
        public int $canalId,
        public int $tipoGestionId,
        public int $resultadoId,
        public ?int $motivoNoContactoId,
        public ?int $causaId,
        public int $usuarioId,
        public ?string $notas,
        public ?DuracionSegundos $duracion,
        public DateTimeImmutable $creadaEn,
        public ?DatosCompromiso $datosCompromiso = null,
    ) {}
}
