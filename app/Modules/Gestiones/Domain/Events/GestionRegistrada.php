<?php

declare(strict_types=1);

namespace App\Modules\Gestiones\Domain\Events;

use App\Modules\Gestiones\Domain\ValueObjects\BanderasResultado;
use App\Modules\Gestiones\Domain\ValueObjects\DatosCompromiso;
use DateTimeImmutable;

final readonly class GestionRegistrada
{
    public function __construct(
        public int $gestionId,
        public string $publicId,
        public int $proyectoId,
        public int $casoId,
        public int $personaId,
        public int $usuarioId,
        public int $resultadoId,
        public int $tipoGestionId,
        public int $canalId,
        public BanderasResultado $banderas,
        public DateTimeImmutable $creadaEn,
        public ?DatosCompromiso $datosCompromiso = null,
    ) {}
}
