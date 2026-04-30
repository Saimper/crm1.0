<?php

declare(strict_types=1);

namespace App\Modules\Servicio\Application\UseCases;

use App\Modules\Compromisos\Application\DTOs\ResolverCompromisoInput;
use App\Modules\Compromisos\Application\UseCases\MarcarCompromisoRoto;

final readonly class MarcarAccionFallida
{
    public function __construct(
        private MarcarCompromisoRoto $nucleo,
    ) {}

    public function execute(ResolverCompromisoInput $input): void
    {
        $this->nucleo->execute($input);
    }
}
