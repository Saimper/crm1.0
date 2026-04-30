<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Application\UseCases;

use App\Modules\Compromisos\Application\DTOs\ResolverCompromisoInput;
use App\Modules\Compromisos\Application\UseCases\CancelarCompromiso;

final readonly class CancelarPromesa
{
    public function __construct(
        private CancelarCompromiso $nucleo,
    ) {}

    public function execute(ResolverCompromisoInput $input): void
    {
        $this->nucleo->execute($input);
    }
}
