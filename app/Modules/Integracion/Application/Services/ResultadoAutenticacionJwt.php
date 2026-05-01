<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Application\Services;

use App\Models\User;
use App\Modules\Integracion\Domain\ValueObjects\PayloadJwt;

final readonly class ResultadoAutenticacionJwt
{
    public function __construct(
        public User $usuario,
        public PayloadJwt $payload,
    ) {}
}
