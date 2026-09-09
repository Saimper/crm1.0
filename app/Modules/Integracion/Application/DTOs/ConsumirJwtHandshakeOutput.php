<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Application\DTOs;

final readonly class ConsumirJwtHandshakeOutput
{
    /**
     * `identificacionNoResuelta` y `tipoIdentificacionCodigo` sólo vienen
     * cuando el JWT traía identificación, había proyecto y no se abrió ficha:
     * es lo que el aterrizaje necesita para ofrecer crear la persona con los
     * datos de la llamada. `identificacionAmbigua` distingue "no existe" de
     * "existe más de una" (misma cadena con dos tipos de identificación).
     */
    public function __construct(
        public int $usuarioId,
        public int $mandanteId,
        public ?int $proyectoId,
        public ?string $redirectPath,
        public ?string $personaPublicId,
        public ?string $casoPublicId = null,
        public ?string $syncRef = null,
        public ?string $identificacionNoResuelta = null,
        public ?string $tipoIdentificacionCodigo = null,
        public bool $identificacionAmbigua = false,
    ) {}
}
