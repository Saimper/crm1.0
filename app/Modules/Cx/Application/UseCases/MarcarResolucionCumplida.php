<?php

declare(strict_types=1);

namespace App\Modules\Cx\Application\UseCases;

use App\Modules\Compromisos\Application\DTOs\ResolverCompromisoInput;
use App\Modules\Compromisos\Application\UseCases\MarcarCompromisoCumplido;

final readonly class MarcarResolucionCumplida
{
    public function __construct(
        private MarcarCompromisoCumplido $nucleo,
    ) {
    }

    public function execute(ResolverCompromisoInput $input): void
    {
        $this->nucleo->execute($input);
    }
}
