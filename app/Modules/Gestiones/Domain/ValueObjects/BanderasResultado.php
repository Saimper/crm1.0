<?php

declare(strict_types=1);

namespace App\Modules\Gestiones\Domain\ValueObjects;

final readonly class BanderasResultado
{
    public function __construct(
        public bool $esContactoEfectivo,
        public bool $requiereCompromiso,
        public bool $requiereCausa,
    ) {
    }
}
